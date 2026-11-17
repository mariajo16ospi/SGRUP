<?php

namespace App\Support;

use App\Models\BudgetLine;
use App\Models\Project;
use App\Models\PurchaseRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Datos del Área financiera (mockups FIN 01–06).
 *
 * Versión de diseño: parte de DemoData y guarda en la sesión los CDP expedidos,
 * pagos registrados, devoluciones, traslados solicitados y documentos subidos.
 */
class FinanceStore
{
    public const DOC_CATEGORIES = ['contractual' => 'Contractuales', 'soporte' => 'Soportes', 'informe' => 'Informes'];

    public const DOC_TYPES = [
        'Contrato o convenio' => 'contractual',
        'Acta de inicio' => 'contractual',
        'Pólizas de cumplimiento' => 'contractual',
        'Acta de liquidación' => 'contractual',
        'Certificado de experiencia (RUP)' => 'contractual',
        'Soporte de pago' => 'soporte',
        'Factura' => 'soporte',
        'Informe técnico' => 'informe',
        'Informe final' => 'informe',
    ];

    /** Documentos que exige la Cámara de Comercio para registrar la experiencia en el RUP. */
    public const RUP_DOCS = ['Contrato o convenio', 'Acta de inicio', 'Acta de liquidación', 'Certificado de experiencia (RUP)'];

    public const RETURN_REASONS = [
        'El valor supera lo cotizado inicialmente',
        'No hay disponibilidad en el rubro',
        'Falta documentación soporte',
        'La compra no corresponde al alcance del proyecto',
    ];

    public const PAYMENT_METHODS = ['Transferencia bancaria', 'Cheque', 'Caja menor'];

    private const SUPPLIERS = ['SC-0225' => 'SolarHuila S.A.S. · NIT 900.456.789-1', 'SC-0228' => 'Eventos y Sabores del Huila · NIT 901.223.110-4'];

    // ---------- Compras ----------

    /** Solicitudes de compra con los cambios hechos en la sesión, de la más reciente a la más antigua. */
    public function purchases(): Collection
    {
        $changes = DemoState::get('fin_purchases', []);
        $d = DemoData::get();

        // Solicitudes creadas por el investigador en la demo
        $created = collect(DemoState::get('inv_purchases', []))->map(function ($r) use ($d) {
            $project = DemoData::project($r['project_id']);
            $line = $project->budgetLines[$r['line']];
            $m = new PurchaseRequest;
            $m->forceFill([
                'id' => $r['id'], 'code' => $r['code'], 'project_id' => $project->id, 'budget_line_id' => $line->id,
                'requester_id' => $r['requester_id'], 'description' => $r['description'], 'value' => $r['value'],
                'original_value' => $r['original_value'], 'status' => 'en_vicerrectoria',
                'submitted_at' => Carbon::parse($r['at']), 'cdp' => null, 'justification' => $r['justification'] ?? null,
            ]);
            $m->exists = true;
            $m->setRelation('project', $project);
            $m->setRelation('budgetLine', $line);
            $m->setRelation('requester', $d->users->firstWhere('id', $r['requester_id']));

            return $m;
        });

        return $d->purchases->concat($created)->map(function (PurchaseRequest $p) use ($changes) {
            $c = clone $p;
            $c->forceFill(array_intersect_key($changes[$p->code] ?? [], array_flip(['status', 'cdp', 'value', 'original_value', 'description'])));
            $c->setAttribute('changes_log', $changes[$p->code]['log'] ?? []);

            return $c;
        })->sortByDesc('submitted_at')->values();
    }

    public function nextPurchaseCode(): string
    {
        return sprintf('SC-%04d', 238 + count(DemoState::get('inv_purchases', [])));
    }

    /** El investigador crea una solicitud: queda en revisión de Vicerrectoría. */
    public function createPurchase(Project $project, array $data): string
    {
        $code = $this->nextPurchaseCode();
        DemoState::push('inv_purchases', [
            'id' => 100 + count(DemoState::get('inv_purchases', [])), 'code' => $code, 'project_id' => $project->id,
            'line' => $data['line'], 'requester_id' => auth()->id(), 'description' => $data['description'],
            'value' => (float) $data['value'], 'original_value' => (float) ($data['original_value'] ?: $data['value']),
            'justification' => $data['justification'] ?? null, 'at' => now()->toDateTimeString(),
        ]);

        return "Solicitud {$code} enviada a Vicerrectoría para revisión técnica.";
    }

    public function purchase(string $code): PurchaseRequest
    {
        return $this->purchases()->firstWhere('code', $code) ?? abort(404);
    }

    public function needsCdp(PurchaseRequest $p): bool
    {
        return $p->status === 'en_financiera' && ! $p->cdp;
    }

    public function needsPayment(PurchaseRequest $p): bool
    {
        return in_array($p->status, ['en_financiera', 'aprobada'], true) && (bool) $p->cdp;
    }

    /** Cambia el estado de una compra y lo deja en su historial (lo usan Vicerrectoría y Financiera). */
    public function update(string $code, array $data, string $logText): void
    {
        $all = DemoState::get('fin_purchases', []);
        $entry = array_merge($all[$code] ?? [], $data);
        $entry['log'][] = ['at' => now()->format('Y-m-d H:i'), 'by' => auth()->user()->name, 'role' => auth()->user()->roleLabel(), 'text' => $logText];
        $all[$code] = $entry;
        DemoState::put('fin_purchases', $all);
    }

    public function nextCdpNumber(): string
    {
        $used = count(array_filter(DemoState::get('fin_purchases', []), fn ($c) => ! empty($c['cdp'])));

        return sprintf('CDP-2026-%04d', 418 + $used);
    }

    public function nextVoucherNumber(): string
    {
        $paid = count(array_filter(DemoState::get('fin_purchases', []), fn ($c) => ($c['status'] ?? '') === 'pagada')) + count(DemoState::get('fin_installments', []));

        return sprintf('CE-2026-%04d', 1187 + $paid);
    }

    public function issueCdp(string $code, array $data): string
    {
        $p = $this->purchase($code);
        abort_unless($this->needsCdp($p), 409, 'La solicitud no está esperando CDP.');
        $line = $this->line($p->budgetLine);
        if ($line['available'] < (float) $p->value) {
            throw ValidationException::withMessages(['cdp' => "No hay disponibilidad: el rubro {$p->budgetLine->name} tiene " . money($line['available']) . ' y la solicitud es de ' . money($p->value) . '.']);
        }
        $this->update($code, ['cdp' => $data['cdp_number']], "Expidió {$data['cdp_number']} · fuente: {$data['source']}");

        return "{$data['cdp_number']} expedido para {$code}. El valor pasó a «Comprometido».";
    }

    public function returnPurchase(string $code, array $data): string
    {
        $p = $this->purchase($code);
        abort_unless($this->needsCdp($p), 409);
        $this->update($code, ['status' => 'devuelta'], "Devolvió a Vicerrectoría: {$data['reason']}. {$data['notes']}");

        return "{$code} devuelta a Vicerrectoría con tus observaciones.";
    }

    public function registerPayment(string $code, array $data): string
    {
        $p = $this->purchase($code);
        abort_unless($this->needsPayment($p), 409, 'La solicitud no tiene pagos pendientes.');
        $this->update($code, ['status' => 'pagada', 'paid' => (float) $data['amount']],
            'Registró pago ' . money($data['amount']) . " · {$data['method']} · {$data['voucher']}");

        return "Pago de {$code} registrado ({$data['voucher']}). Quedó como ejecutado en el rubro {$p->budgetLine->name}.";
    }

    public function supplier(string $code): string
    {
        return self::SUPPLIERS[$code] ?? '';
    }

    // ---------- Desembolsos de contrato ----------

    public function installments(): Collection
    {
        $paid = DemoState::get('fin_installments', []);
        $drones = DemoData::get()->projects['drones'];

        return collect([[
            'key' => 'CUOTA-3', 'label' => 'Cuota 3', 'concept' => 'Pago por avance aprobado', 'line' => 'Desembolso del contrato',
            'project' => $drones, 'value' => 250_000_000, 'date' => Carbon::parse('2026-10-29'),
        ]])->map(fn ($i) => $i + ['paid' => isset($paid[$i['key']])]);
    }

    public function payInstallment(string $key, array $data): string
    {
        $i = $this->installments()->firstWhere('key', $key) ?? abort(404);
        abort_if($i['paid'], 409);
        DemoState::put("fin_installments.{$key}", $data + ['at' => now()->format('Y-m-d H:i')]);

        return "{$i['label']} de {$i['project']->name} registrada ({$data['voucher']}).";
    }

    /** Bandeja «Por gestionar» de FIN 01: CDP por expedir y pagos por registrar. */
    public function toManage(): Collection
    {
        $items = collect();
        foreach ($this->purchases()->sortBy('submitted_at') as $p) {
            if ($this->needsCdp($p)) {
                $items->push(['kind' => 'cdp', 'code' => $p->code, 'date' => $p->submitted_at, 'concept' => $p->description,
                    'project' => $p->project, 'line' => $p->budgetLine->name, 'value' => (float) $p->value, 'state' => 'Requiere CDP', 'tone' => 'amber']);
            }
        }
        foreach ($this->purchases()->sortBy('submitted_at') as $p) {
            if ($this->needsPayment($p)) {
                $items->push(['kind' => 'pago', 'code' => $p->code, 'date' => $p->submitted_at, 'concept' => $p->description,
                    'project' => $p->project, 'line' => $p->budgetLine->name, 'value' => (float) $p->value, 'state' => $p->cdp, 'tone' => 'green']);
            }
        }
        foreach ($this->installments()->where('paid', false) as $i) {
            $items->push(['kind' => 'cuota', 'code' => $i['key'], 'label' => $i['label'], 'date' => $i['date'], 'concept' => $i['concept'],
                'project' => $i['project'], 'line' => $i['line'], 'value' => (float) $i['value'], 'state' => 'Avance aprobado', 'tone' => 'green']);
        }

        return $items;
    }

    /** Sobrecostos: compras por encima de lo cotizado y rubros que superan lo asignado. */
    public function overruns(): Collection
    {
        $rows = collect();
        foreach ($this->purchases()->whereNotIn('status', ['pagada', 'rechazada'])->sortBy('submitted_at') as $p) {
            if ($pct = $p->overrunPercent()) {
                $rows->push(['project' => $p->project, 'concept' => "{$p->code} · {$p->description}", 'planned' => (float) $p->original_value,
                    'actual' => (float) $p->value, 'pct' => $pct, 'state' => $p->overrunPercent() && $p->status === 'en_vicerrectoria' ? 'En Vicerrectoría' : $p->statusLabel(), 'tone' => 'amber']);
            }
        }
        foreach ($this->activeProjects() as $project) {
            foreach ($project->budgetLines as $l) {
                $f = $this->line($l);
                if ($f['executed'] + $f['committed'] > $f['budgeted'] + 1) {
                    $rows->push(['project' => $project, 'concept' => "Rubro {$l->name}", 'planned' => $f['budgeted'],
                        'actual' => $f['executed'] + $f['committed'], 'pct' => (int) round(($f['executed'] + $f['committed']) / $f['budgeted'] * 100 - 100),
                        'state' => 'Excede rubro', 'tone' => 'red']);
                }
            }
        }

        return $rows;
    }

    // ---------- Presupuesto ----------

    public function activeProjects(): Collection
    {
        return DemoData::get()->projects->where('status', 'activo')->values();
    }

    /** Cifras de un rubro: asignado, ejecutado (más pagos de la sesión), comprometido (CDP sin pagar) y disponible. */
    public function line(BudgetLine $line): array
    {
        $purchases = $this->purchases()->where('budget_line_id', $line->id);
        $changes = DemoState::get('fin_purchases', []);
        $paidNow = $purchases->sum(fn ($p) => $changes[$p->code]['paid'] ?? 0);
        $committed = $purchases->filter(fn ($p) => $this->needsPayment($p))->sum('value');
        $budgeted = (float) $line->budgeted + $this->transferDelta($line);
        $executed = (float) $line->executed + $paidNow;

        return [
            'name' => $line->name, 'budgeted' => $budgeted, 'executed' => $executed, 'committed' => (float) $committed,
            'available' => $budgeted - $executed - $committed,
            'use' => $budgeted > 0 ? (int) round(($executed + $committed) / $budgeted * 100) : 0,
        ];
    }

    /** Totales de un proyecto con su planeado a la fecha según la Curva S. */
    public function projectTotals(Project $project): array
    {
        $lines = $project->budgetLines->map(fn ($l) => $this->line($l));
        $last = SCurve::forProject($project)->lastExecuted();
        $value = (float) $project->value;
        $executed = $lines->sum('executed');
        $committed = $lines->sum('committed');

        return [
            'project' => $project, 'lines' => $lines->values(), 'value' => $value,
            'planned' => (float) ($last['planned_amount'] ?? 0), 'planned_pct' => (int) round($last['planned'] ?? 0),
            'executed' => $executed, 'committed' => $committed, 'available' => $value - $executed - $committed,
            'executed_pct' => $value > 0 ? (int) round($executed / $value * 100) : 0,
        ];
    }

    /** Ejecución por rubro sumando todos los proyectos activos. */
    public function byLine(): Collection
    {
        return collect(DemoData::BUDGET_LINES)->map(function ($name) {
            $rows = $this->activeProjects()->map(fn ($p) => $this->line($p->budgetLines[$name]));

            return ['name' => $name, 'budgeted' => $rows->sum('budgeted'), 'executed' => $rows->sum('executed')];
        });
    }

    /** Movimientos de gasto de un proyecto: ejecución mensual repartida por rubro y pagos de compras. */
    public function movements(Project $project): Collection
    {
        $points = $project->curvePoints->whereNotNull('executed_cumulative')->values();
        $lines = $project->budgetLines->values();
        $base = max(1, $lines->sum(fn ($l) => (float) $l->executed));
        $rows = collect();
        $prev = 0;
        foreach ($points as $pt) {
            $delta = (float) $pt->executed_cumulative - $prev;
            $prev = (float) $pt->executed_cumulative;
            foreach ($lines as $l) {
                if ($l->name === 'Equipos' || $l->executed <= 0) {
                    continue;
                }
                $rows->push(['date' => $pt->month, 'line' => $l->name, 'description' => 'Ejecución ' . Str::lower($l->name),
                    'origin' => 'Avance mensual', 'value' => round($delta * $l->executed / $base, -4)]);
            }
        }
        $changes = DemoState::get('fin_purchases', []);
        foreach ($this->purchases()->where('project_id', $project->id)->where('status', 'pagada') as $p) {
            $rows->push(['date' => isset($changes[$p->code]['paid']) ? today() : $p->submitted_at->copy()->addDays(20), 'line' => $p->budgetLine->name,
                'description' => $p->description, 'origin' => "Compra {$p->code}", 'value' => (float) $p->value]);
        }

        return $rows->sortByDesc('date')->values();
    }

    // ---------- Traslados ----------

    /** Traslados de DemoData y los solicitados en la demo, con su estado (pendiente / aprobado / rechazado). */
    public function allTransfers(): Collection
    {
        $decided = DemoState::get('vic_transfers', []);
        $d = DemoData::get();
        $base = $d->transfers->map(fn ($t) => [
            'code' => $t->code, 'project_id' => $t->project_id, 'from' => $t->fromLine->name, 'to' => $t->toLine->name,
            'amount' => (float) $t->amount, 'reason' => $t->reason, 'by' => $t->requester->name, 'date' => $t->submitted_at,
        ]);
        $new = collect(DemoState::get('fin_transfers', []))->map(fn ($t) => $t + ['date' => Carbon::parse($t['at'])]);

        return $base->concat($new)->map(fn ($t) => $t + ['status' => $decided[$t['code']] ?? 'pendiente'])->values();
    }

    private function transferDelta(BudgetLine $line): float
    {
        return $this->allTransfers()->where('status', 'aprobado')->where('project_id', $line->project_id)
            ->sum(fn ($t) => ($t['to'] === $line->name ? $t['amount'] : 0) - ($t['from'] === $line->name ? $t['amount'] : 0));
    }

    public function transfers(Project $project): Collection
    {
        return $this->allTransfers()->where('project_id', $project->id)->sortByDesc('date')->values();
    }

    public function requestTransfer(Project $project, array $data): string
    {
        $from = $project->budgetLines[$data['from']] ?? abort(422);
        $available = $this->line($from)['available'];
        if ((float) $data['amount'] > $available) {
            throw ValidationException::withMessages(['amount' => "El rubro {$data['from']} solo tiene " . money($available) . ' disponible.']);
        }
        $code = sprintf('TP-%04d', 13 + count(DemoState::get('fin_transfers', [])));
        DemoState::push('fin_transfers', [
            'code' => $code, 'project_id' => $project->id, 'from' => $data['from'], 'to' => $data['to'],
            'amount' => (float) $data['amount'], 'reason' => ($data['reason'] ?? null) ?: null, 'by' => auth()->user()->name,
            'at' => now()->format('Y-m-d H:i'),
        ]);

        return "Traslado {$code} por " . money($data['amount']) . " solicitado. Queda pendiente de aprobación de Vicerrectoría.";
    }

    // ---------- Documentos ----------

    public function documents(): Collection
    {
        $d = DemoData::get();
        $validated = DemoState::get('fin_docs_valid', []);
        $docs = collect();
        $n = 0;
        $add = function (Project $p, string $type, string $name, string $ext, string $by, Carbon $date, float $mb, bool $ok) use (&$docs, &$n) {
            $n++;
            $docs->push(['id' => "d{$n}", 'project' => $p, 'type' => $type, 'category' => self::DOC_TYPES[$type], 'name' => $name,
                'ext' => $ext, 'by' => $by, 'date' => $date, 'size' => $mb, 'validated' => $ok, 'path' => null]);
        };
        $vic = $d->users['marcela']->name;
        $fin = $d->users['carlos']->name;
        $reportSizes = ['tic' => 1.3, 'drones' => 1.7, 'energia' => 1.6, 'emprende' => 1.5, 'tatacoa' => 1.6];
        $zipSizes = ['tic' => 1.8, 'drones' => 0.8, 'energia' => 0.3, 'emprende' => 0.6, 'residuos' => 1.1, 'tatacoa' => 0.4];

        // Documentos de ejemplo; los proyectos creados en la demo solo tienen los que se suban
        foreach ($d->projects->except('garzon')->reject(fn ($p) => DemoData::isNew($p)) as $key => $p) {
            $s = $p->start_date;
            $add($p, 'Contrato o convenio', "{$p->contractLabel()} {$p->code}.pdf", 'PDF', $vic, $s->copy()->subDays(10), 2.4, true);
            $add($p, 'Acta de inicio', "Acta de inicio {$p->code}.pdf", 'PDF', $p->coordinator->name, $s->copy()->addDays(3), 0.6, true);
            if ($p->status === 'activo') {
                $add($p, 'Pólizas de cumplimiento', 'Pólizas de cumplimiento.pdf', 'PDF', $fin, $s->copy()->addDays(6), 1.1, true);
                $add($p, 'Soporte de pago', 'Soportes de pago Sep 26.zip', 'ZIP', $fin, Carbon::parse('2026-09-25'), $zipSizes[$key], true);
                if (isset($reportSizes[$key])) {
                    $add($p, 'Informe técnico', 'Informe técnico septiembre 2026.docx', 'DOCX', $p->coordinator->name, Carbon::parse('2026-09-28'), $reportSizes[$key], false);
                }
            } else {
                $e = $p->end_date;
                $add($p, 'Soporte de pago', 'Soportes de pago finales.zip', 'ZIP', $fin, $e->copy()->subDays(20), 2.2, true);
                $add($p, 'Informe final', 'Informe final.pdf', 'PDF', $p->coordinator->name, $e->copy()->subDays(10), 3.4, true);
                $add($p, 'Acta de liquidación', "Acta de liquidación {$p->code}.pdf", 'PDF', $vic, $e->copy()->addDays(15), 0.7, true);
                $add($p, 'Certificado de experiencia (RUP)', 'Certificado de experiencia.pdf', 'PDF', $vic, $e->copy()->addDays(25), 0.4, true);
            }
        }
        foreach (DemoState::get('fin_docs_new', []) as $doc) {
            $doc['project'] = DemoData::project($doc['project_id']);
            $doc['date'] = Carbon::parse($doc['date']);
            $docs->push($doc);
        }

        return $docs->map(fn ($doc) => in_array($doc['id'], $validated, true) ? ['validated' => true] + $doc : $doc)
            ->sortByDesc('date')->values();
    }

    public function document(string $id): array
    {
        return $this->documents()->firstWhere('id', $id) ?? abort(404);
    }

    public function validateDocument(string $id): string
    {
        $doc = $this->document($id);
        abort_if($doc['validated'], 409);
        DemoState::push('fin_docs_valid', $id);

        return "Documento «{$doc['name']}» validado.";
    }

    public function upload(Project $project, string $type, UploadedFile $file): string
    {
        $path = $file->store('demo-uploads');
        DemoState::push('fin_docs_new', [
            'id' => 'u' . Str::random(8), 'project_id' => $project->id, 'type' => $type, 'category' => self::DOC_TYPES[$type],
            'name' => $file->getClientOriginalName(), 'ext' => Str::upper($file->getClientOriginalExtension() ?: 'FILE'),
            'by' => auth()->user()->name, 'date' => now()->toDateTimeString(), 'size' => round($file->getSize() / 1_048_576, 1),
            'validated' => false, 'path' => $path,
        ]);

        return "«{$file->getClientOriginalName()}» subido a {$project->name}. Queda en revisión.";
    }

    /** Estado documental RUP: cuántos de los 4 documentos requeridos están validados. */
    public function rupStatus(): Collection
    {
        $docs = $this->documents();

        return DemoData::get()->projects->whereIn('status', ['activo', 'liquidado'])->map(fn ($p) => [
            'project' => $p,
            'done' => collect(self::RUP_DOCS)->filter(fn ($t) => $docs->where('project.id', $p->id)->where('type', $t)->where('validated', true)->isNotEmpty())->count(),
        ])->values();
    }

    // ---------- Alertas ----------

    public function alerts(): Collection
    {
        return DemoData::get()->alerts->where('resolved', false)->values();
    }
}
