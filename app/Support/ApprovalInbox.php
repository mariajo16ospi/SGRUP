<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bandeja de aprobaciones de Vicerrectoría: compras, avances mensuales,
 * traslados presupuestales y proyectos nuevos pendientes de decisión.
 *
 * Versión de diseño: las decisiones se guardan en DemoState, así el Área
 * financiera ve las compras que aprueba Vicerrectoría.
 */
class ApprovalInbox
{
    /** Días desde la radicación a partir de los cuales una solicitud se marca vencida. */
    public const DUE_DAYS = 18;

    public const TYPES = [
        'compra' => ['Compra', 'blue', 'Compras'],
        'avance' => ['Avance', 'green', 'Avances'],
        'traslado' => ['Traslado', 'amber', 'Traslados'],
        'proyecto' => ['Proyecto nuevo', 'gray', 'Proyectos nuevos'],
    ];

    public const REJECT_REASONS = [
        'No corresponde a los productos del convenio',
        'El valor no está justificado',
        'Ya existe una solicitud para el mismo bien o servicio',
        'El proyecto no tiene saldo para esta compra',
    ];

    public const ADJUST_REASONS = [
        'El valor supera lo cotizado inicialmente',
        'Faltan cotizaciones o soportes',
        'La descripción del bien o servicio no es clara',
        'Debe cargarse a otro rubro',
    ];

    public function __construct(private FinanceStore $finance, private ProjectTracking $tracking) {}

    /** @return Collection<int, array> ordenadas de la más antigua a la más reciente */
    public function pending(): Collection
    {
        $d = DemoData::get();
        $items = collect();

        foreach ($this->finance->purchases()->filter(fn ($c) => $c->awaitingVicerrectoria()) as $c) {
            $overrun = $c->overrunPercent();
            $items->push($this->item('compra', $c->id, "{$c->code} · {$c->description}",
                $c->status === 'devuelta' ? 'Devuelta por el área financiera: ' . Str::after(collect($c->changes_log)->last()['text'] ?? '', 'Devolvió a Vicerrectoría: ') : null,
                $overrun ? "Excede rubro +{$overrun} %" : ($this->finance->line($c->budgetLine)['available'] < $c->value ? 'Sin saldo en el rubro' : null),
                $c->project, $c->requester->name, $c->value, $c->submitted_at, route('vic.compras', ['ver' => $c->code])));
        }

        $decided = DemoState::get('vic_progress', []);
        foreach ($this->tracking->progressRequests()->where('status', 'pendiente') as $a) {
            if (isset($decided[$a->id])) {
                continue;
            }
            $items->push($this->item('avance', $a->id, 'Avance de ' . month_long($a->period),
                "Técnico {$a->technical_progress} % (+{$a->technical_delta} pts) · {$a->supports_count} soportes", null,
                $a->project, $a->submitter->name, $a->amount, $a->submitted_at, route('vic.proyectos.avances', [$a->project, 'revisar' => 1])));
        }

        foreach ($this->finance->allTransfers()->where('status', 'pendiente') as $t) {
            $project = $d->projects->firstWhere('id', $t['project_id']);
            $items->push($this->item('traslado', $this->transferId($t['code']), "{$t['code']} · {$t['from']} → {$t['to']}",
                $t['reason'], null, $project, $t['by'], $t['amount'], $t['date'], route('vic.proyectos.presupuesto', $project)));
        }

        $projects = DemoState::get('vic_projects', []);
        foreach ($d->projects->where('status', 'propuesto') as $p) {
            if (isset($projects[$p->id])) {
                continue;
            }
            $items->push($this->item('proyecto', $p->id, $p->name,
                "{$p->modalityLabel()} con {$p->entity}", null, null, $p->coordinator->name, $p->value, $p->created_at,
                route('vic.aprobaciones', ['revisar' => $p->id])));
        }

        return $items->sortBy('received')->values();
    }

    private function transferId(string $code): int
    {
        return (int) substr($code, 3);
    }

    private function item(string $type, int $id, string $title, ?string $subtitle, ?string $flag, ?Project $project, string $requester, $value, Carbon $received, ?string $reviewUrl): array
    {
        [$label, $tone] = self::TYPES[$type];
        $age = (int) $received->copy()->startOfDay()->diffInDays(today());

        return compact('type', 'id', 'title', 'subtitle', 'flag', 'project', 'requester', 'value', 'received', 'label', 'tone') + [
            'overdue' => $age >= self::DUE_DAYS,
            'review_url' => $reviewUrl,
        ];
    }

    /** Aprueba una solicitud aplicando las reglas de negocio. Devuelve el mensaje de confirmación. */
    public function approve(string $type, int $id, ?string $comment = null): string
    {
        $d = DemoData::get();

        return match ($type) {
            'compra' => $this->approvePurchase($this->finance->purchases()->firstWhere('id', $id) ?? abort(404), $comment),
            'avance' => $this->approveProgress($this->tracking->progressRequests()->firstWhere('id', $id) ?? abort(404), $comment),
            'traslado' => $this->approveTransfer($id, $comment),
            'proyecto' => $this->approveProject($d->projects->firstWhere('id', $id) ?? abort(404)),
            default => abort(404),
        };
    }

    private function approvePurchase($c, ?string $comment): string
    {
        abort_unless($c->awaitingVicerrectoria(), 409, 'La compra ya no está pendiente.');
        // Control automático de límites presupuestales
        $available = $this->finance->line($c->budgetLine)['available'];
        if ($available < (float) $c->value) {
            throw ValidationException::withMessages([
                'approval' => "No se puede aprobar {$c->code}: el rubro {$c->budgetLine->name} tiene disponible " . money($available)
                    . ' y la compra solicita ' . money($c->value) . '. Aprueba primero un traslado presupuestal o solicita un ajuste.',
            ]);
        }
        $this->finance->update($c->code, ['status' => 'en_financiera'], 'Aprobada por Vicerrectoría' . ($comment ? ": {$comment}" : '. Proceder con el CDP.'));

        return "{$c->code} aprobada. Pasó al área financiera para expedir el CDP.";
    }

    /** Rechazar (definitivo) o devolver para ajuste una compra. */
    public function decidePurchase(string $code, string $decision, string $reason, string $notes): string
    {
        $c = $this->finance->purchase($code);
        abort_unless($c->awaitingVicerrectoria(), 409, 'La compra ya no está pendiente.');
        if ($decision === 'rechazar') {
            $this->finance->update($code, ['status' => 'rechazada'], "Rechazada por Vicerrectoría: {$reason}. {$notes}");

            return "{$code} rechazada. Quedó registrada en la trazabilidad del proyecto.";
        }
        $this->finance->update($code, ['status' => 'requiere_ajuste'], "Devuelta por Vicerrectoría para ajuste: {$reason}. {$notes}");

        return "{$code} devuelta a {$c->requester->name} para ajuste.";
    }

    private function approveProgress($a, ?string $comment): string
    {
        abort_if(isset(DemoState::get('vic_progress', [])[$a->id]), 409, 'El avance ya fue revisado.');
        DemoState::put("vic_progress.{$a->id}", 'aprobado');
        $this->tracking->log($a->project, 'Aprobó el avance de ' . month_long($a->period), $comment);

        return 'Avance de ' . month_long($a->period) . " de {$a->project->name} aprobado. Se habilitan pagos por " . money($a->amount) . '.';
    }

    public function returnProgress(int $id, string $comment): string
    {
        $a = $this->tracking->progressRequests()->firstWhere('id', $id) ?? abort(404);
        abort_if(isset(DemoState::get('vic_progress', [])[$a->id]), 409, 'El avance ya fue revisado.');
        DemoState::put("vic_progress.{$a->id}", 'devuelto');
        $this->tracking->log($a->project, 'Devolvió con observaciones el avance de ' . month_long($a->period), $comment);

        return 'Avance de ' . month_long($a->period) . " devuelto a {$a->submitter->name} con observaciones.";
    }

    private function approveTransfer(int $id, ?string $comment): string
    {
        $t = $this->finance->allTransfers()->first(fn ($t) => $this->transferId($t['code']) === $id) ?? abort(404);
        abort_unless($t['status'] === 'pendiente', 409, 'El traslado ya fue revisado.');
        $project = DemoData::project($t['project_id']);
        $from = $project->budgetLines[$t['from']];
        $available = $this->finance->line($from)['available'];
        if ($available < $t['amount']) {
            throw ValidationException::withMessages([
                'approval' => "No se puede aprobar {$t['code']}: el rubro {$t['from']} solo tiene " . money($available) . ' disponible.',
            ]);
        }
        DemoState::put("vic_transfers.{$t['code']}", 'aprobado');
        $this->tracking->log($project, "Aprobó el traslado {$t['code']}", "{$t['from']} → {$t['to']} · " . money($t['amount']) . ($comment ? " · {$comment}" : ''));

        return "{$t['code']} aprobado. Se trasladaron " . money($t['amount']) . " de {$t['from']} a {$t['to']}.";
    }

    /** Proyecto propuesto que espera la decisión de Vicerrectoría. */
    public function proposal(int $id): Project
    {
        $p = DemoData::get()->projects->firstWhere('id', $id);
        abort_unless($p && $p->status === 'propuesto', 404, 'El proyecto ya fue revisado.');

        return $p;
    }

    private function approveProject(Project $p): string
    {
        abort_unless($p->status === 'propuesto', 409, 'El proyecto ya fue revisado.');
        DemoState::put("vic_projects.{$p->id}", 'aprobado');
        $this->tracking->log($p, 'Aprobó el proyecto', 'Inicia en fase de Preinversión');

        return "Proyecto «{$p->name}» aprobado. Inicia en fase de Preinversión.";
    }

    public function rejectProject(int $id, string $reason): string
    {
        $p = $this->proposal($id);
        DemoState::put("vic_projects.{$p->id}", 'rechazado');
        $this->tracking->log($p, 'Rechazó la propuesta de proyecto', $reason);

        return "Propuesta «{$p->name}» rechazada. {$p->coordinator->name} verá el motivo en la trazabilidad.";
    }
}
