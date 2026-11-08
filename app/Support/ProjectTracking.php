<?php

namespace App\Support;

use App\Models\MonthlyProgress;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seguimiento técnico de los proyectos (mockups VIC 06, 07, 10, 11 y 12):
 * productos y metas, avances mensuales, trazabilidad y cronograma.
 */
class ProjectTracking
{
    public const PRODUCT_TYPES = ['Documento', 'Equipo', 'Capacitación', 'Visita técnica', 'Software', 'Evento', 'Publicación'];

    /** Horizonte de «próximos» días para las entregas por vencer. */
    public const SOON_DAYS = 30;

    public function __construct(private FinanceStore $finance) {}

    // ---------- Productos ----------

    /** Productos con avance y estado calculados al día de hoy. */
    public function products(?Project $project = null): Collection
    {
        $d = DemoData::get();
        $done = DemoState::get('inv_products', []);
        $all = $d->products->concat(collect(DemoState::get('vic_products', []))->map(fn ($p) => ['due' => Carbon::parse($p['due'])] + $p))
            ->map(fn ($p) => isset($done[$p['id']]) ? ['done' => $done[$p['id']]] + $p : $p);

        return $all
            ->when($project, fn ($c) => $c->where('project_id', $project->id))
            ->map(function ($p) use ($d) {
                $p['project'] = $d->projects->firstWhere('id', $p['project_id']);
                $p['progress'] = $p['target'] > 0 ? (int) round(min(1, $p['done'] / $p['target']) * 100) : 0;
                $days = (int) today()->diffInDays($p['due'], false);
                [$p['status'], $p['status_label'], $p['tone']] = match (true) {
                    $p['progress'] >= 100 => ['entregado', 'Entregado', 'green'],
                    $days < 0 => ['vencido', 'Vencido hace ' . abs($days) . ' d', 'red'],
                    $days <= self::SOON_DAYS => ['proximo', $days === 0 ? 'Vence hoy' : "Faltan {$days} d", 'amber'],
                    default => ['pendiente', 'Pendiente', 'gray'],
                };

                return $p;
            })
            ->sortBy('due')->values();
    }

    /** Cumplimiento de metas: promedio simple del avance de los productos. */
    public function compliance(Project $project): int
    {
        $products = $this->products($project);

        return $products->isEmpty() ? (int) $project->technical_progress : (int) round($products->avg('progress'));
    }

    public function addProduct(Project $project, array $data): string
    {
        $n = count(DemoState::get('vic_products', []));
        DemoState::push('vic_products', [
            'id' => 1000 + $n, 'project_id' => $project->id, 'name' => $data['name'], 'type' => $data['type'],
            'done' => 0, 'target' => (int) $data['target'], 'unit' => $data['unit'], 'due' => $data['due'],
        ]);
        $this->log($project, "Agregó el producto «{$data['name']}»", "Meta {$data['target']} {$data['unit']} · entrega " . date_es(Carbon::parse($data['due'])));

        return "Producto «{$data['name']}» agregado a {$project->name}.";
    }

    // ---------- Avances mensuales ----------

    /**
     * Reportes mensuales del proyecto. El gasto del mes es la ejecución de la Curva S
     * menos las compras pagadas ese mes (se registran aparte).
     */
    public function reports(Project $project): Collection
    {
        $d = DemoData::get();
        $decided = DemoState::get('vic_progress', []);
        $pending = $this->progressRequests()->where('project_id', $project->id)->keyBy(fn ($p) => $p->period->format('Y-m'));
        $paidPurchases = $this->finance->purchases()->where('project_id', $project->id)->where('status', 'pagada');
        // Gestión de residuos no ha cargado el avance de septiembre (alerta informativa)
        $lastMonth = $project->code === 'CONV-022-2026' ? Carbon::parse('2026-08-01') : null;

        $rows = collect();
        $prev = 0;
        foreach ($project->curvePoints->whereNotNull('executed_cumulative') as $pt) {
            if ($lastMonth && $pt->month > $lastMonth) {
                break;
            }
            $purchases = $paidPurchases->filter(fn ($p) => $p->submitted_at->isSameMonth($pt->month))->sum('value');
            $spent = (float) $pt->executed_cumulative - $prev - $purchases;
            $prev = (float) $pt->executed_cumulative;
            $key = $pt->month->format('Y-m');
            $req = $pending[$key] ?? null;
            $status = $req ? ($decided[$req->id] ?? 'pendiente') : 'aprobado';
            $rows->push([
                'period' => $pt->month, 'by' => $req?->submitter ?? $project->coordinator,
                'date' => $pt->month->copy()->day(min(28, $pt->month->daysInMonth)),
                'amount' => $req ? (float) $req->amount : round($spent, -4),
                'notes' => 'Avance del periodo según cronograma. Actividades desarrolladas con normalidad.',
                'status' => $status, 'request' => $req,
            ]);
        }

        // Avances cargados en la demo para meses sin ejecución en la curva (p. ej. octubre 2026)
        foreach ($pending as $key => $req) {
            if (! $rows->contains(fn ($r) => $r['period']->format('Y-m') === $key)) {
                $rows->push([
                    'period' => $req->period, 'by' => $req->submitter, 'date' => $req->submitted_at, 'amount' => (float) $req->amount,
                    'notes' => $req->notes ?: 'Avance del periodo según cronograma.', 'status' => $decided[$req->id] ?? 'pendiente', 'request' => $req,
                ]);
            }
        }

        return $rows->sortByDesc('period')->values();
    }

    /**
     * Avances enviados a revisión: los de ejemplo y los que carga el investigador en la demo.
     * El estado de la decisión de Vicerrectoría se lee aparte (vic_progress).
     */
    public function progressRequests(): Collection
    {
        $d = DemoData::get();
        $created = collect(DemoState::get('inv_reports', []))->map(function ($r) use ($d) {
            $m = new MonthlyProgress;
            $m->forceFill([
                'id' => $r['id'], 'project_id' => $r['project_id'], 'submitted_by' => $r['by'], 'period' => Carbon::parse($r['period']),
                'technical_progress' => $r['technical'], 'technical_delta' => $r['delta'], 'supports_count' => $r['supports'],
                'amount' => $r['amount'], 'status' => 'pendiente', 'submitted_at' => Carbon::parse($r['at']), 'notes' => $r['notes'],
            ]);
            $m->exists = true;
            $m->setRelation('project', DemoData::project($r['project_id']));
            $m->setRelation('submitter', $d->users->firstWhere('id', $r['by']));

            return $m;
        });

        return $d->progress->concat($created)->values();
    }

    /** Próximo avance por cargar: el primer mes ya cerrado sin reporte. Vence el día 5 del mes siguiente. */
    public function nextReport(Project $project): ?array
    {
        $loaded = $this->reports($project)->map(fn ($r) => $r['period']->format('Y-m'))->all();
        for ($m = $project->start_date->copy()->startOfMonth(); $m <= $project->end_date; $m->addMonth()) {
            if (! in_array($m->format('Y-m'), $loaded, true) && $m->copy()->endOfMonth() < today()->copy()->endOfMonth()) {
                $due = $m->copy()->addMonth()->day(5);

                return ['period' => $m->copy(), 'due' => $due, 'days' => (int) today()->diffInDays($due, false)];
            }
        }

        return null;
    }

    /** El investigador carga el avance mensual: actualiza los productos y queda en revisión. */
    public function addReport(Project $project, array $data, int $supports): string
    {
        $before = $this->compliance($project);
        foreach ($data['products'] ?? [] as $id => $done) {
            if ($done !== null && $done !== '') {
                DemoState::put('inv_products.' . (int) $id, max(0, (int) $done));
            }
        }
        $after = $this->compliance($project);
        $amount = collect($data['expenses'] ?? [])->sum(fn ($e) => (float) ($e['value'] ?? 0));
        $n = count(DemoState::get('inv_reports', []));
        DemoState::push('inv_reports', [
            'id' => 100 + $n, 'project_id' => $project->id, 'by' => auth()->id(), 'period' => $data['period'],
            'technical' => $after, 'delta' => $after - $before, 'supports' => $supports, 'amount' => $amount,
            'notes' => $data['notes'], 'expenses' => array_values($data['expenses'] ?? []), 'at' => now()->toDateTimeString(),
        ]);
        $this->log($project, 'Cargó el avance mensual de ' . month_long(Carbon::parse($data['period'])), 'Técnico ' . $after . ' % · gasto ' . money($amount));

        return 'Avance de ' . month_long(Carbon::parse($data['period'])) . ' enviado a Vicerrectoría para revisión.';
    }

    public function updateProduct(Project $project, int $id, int $done): string
    {
        $p = $this->products($project)->firstWhere('id', $id) ?? abort(404);
        DemoState::put("inv_products.{$id}", max(0, $done));
        $this->log($project, "Actualizó el avance de «{$p['name']}»", "{$done} / {$p['target']} {$p['unit']}");

        return "Avance de «{$p['name']}» actualizado a {$done} / {$p['target']} {$p['unit']}.";
    }

    /** Calendario de reportes: meses de vigencia con su estado (cargado, pendiente o próximo). */
    public function reportCalendar(Project $project): Collection
    {
        $reports = $this->reports($project)->keyBy(fn ($r) => $r['period']->format('Y-m'));
        $months = collect();
        for ($m = $project->start_date->copy()->startOfMonth(); $m <= $project->end_date; $m->addMonth()) {
            $r = $reports[$m->format('Y-m')] ?? null;
            $months->push(['month' => $m->copy(), 'state' => match (true) {
                $r && $r['status'] === 'pendiente' => ['Por revisar', 'amber'],
                $r && $r['status'] === 'devuelto' => ['Devuelto', 'red'],
                (bool) $r => ['Cargado', 'green'],
                $m->copy()->endOfMonth() < today() => ['Sin cargar', 'red'],
                default => ['Próximo', 'gray'],
            }]);
        }

        return $months;
    }

    // ---------- Trazabilidad ----------

    public function timeline(Project $project): Collection
    {
        $d = DemoData::get();
        $vic = $d->users['marcela'];
        $events = collect();
        $add = fn (Carbon $at, string $who, string $role, string $text) => $events->push(compact('at', 'who', 'role', 'text'));

        $add($project->start_date->copy()->setTime(8, 0), $project->coordinator->name, 'Investigador / Coordinador', "Firmó el acta de inicio del {$project->contractLabel()} {$project->code}");
        foreach ($this->finance->purchases()->where('project_id', $project->id) as $p) {
            $add($p->submitted_at->copy()->setTime(9, 10), $p->requester->name, 'Investigador / Coordinador',
                "Creó la solicitud de compra {$p->code} ({$p->description}, " . money($p->value) . ')');
            if (! in_array($p->status, ['en_vicerrectoria'], true) && ! collect($p->changes_log)->contains(fn ($l) => str_contains($l['text'], 'Vicerrectoría'))) {
                $text = match ($p->status) {
                    'rechazada' => "Rechazó la solicitud {$p->code}",
                    'requiere_ajuste' => "Devolvió para ajuste la solicitud {$p->code}",
                    default => "Aprobó la solicitud {$p->code}",
                };
                $add($p->submitted_at->copy()->addDays(2)->setTime(15, 20), $vic->name, 'Vicerrectoría / Subdirección', $text);
            }
            foreach ($p->changes_log as $log) {
                $add(Carbon::parse($log['at']), $log['by'], $log['role'] ?? 'Área financiera', "{$p->code} · {$log['text']}");
            }
        }
        foreach ($this->reports($project) as $r) {
            $add($r['date']->copy()->setTime(17, 30), $r['by']->name, 'Investigador / Coordinador', 'Cargó el avance mensual de ' . month_long($r['period']) . ' (' . money($r['amount']) . ')');
            if ($r['status'] === 'aprobado' && ! $r['request']) {
                $add($r['date']->copy()->addDays(3)->setTime(11, 0), $vic->name, 'Vicerrectoría / Subdirección', 'Aprobó el avance de ' . month_long($r['period']));
            }
        }
        foreach ($this->finance->documents()->where('project.id', $project->id) as $doc) {
            $add($doc['date']->copy()->setTime(10, 0), $doc['by'], 'Documentos', "Subió el documento «{$doc['name']}»");
        }
        foreach ($this->finance->transfers($project) as $t) {
            $add($t['date'], $t['by'], 'Traslados', "Solicitó el traslado {$t['code']}: {$t['from']} → {$t['to']} (" . money($t['amount']) . ')');
        }
        foreach (collect(DemoState::get('vic_log', []))->where('project_id', $project->id) as $l) {
            $add(Carbon::parse($l['at']), $l['who'], $l['role'], $l['text'] . ($l['detail'] ? " · {$l['detail']}" : ''));
        }

        return $events->sortByDesc('at')->values();
    }

    /** Registra una acción de Vicerrectoría en la trazabilidad del proyecto. */
    public function log(Project $project, string $text, ?string $detail = null): void
    {
        DemoState::push('vic_log', [
            'project_id' => $project->id, 'at' => now()->format('Y-m-d H:i'), 'who' => auth()->user()->name,
            'role' => auth()->user()->roleLabel(), 'text' => $text, 'detail' => $detail,
        ]);
    }

    // ---------- Cronograma ----------

    /** Rango de meses que cubre el cronograma de los proyectos dados. */
    public function months(Collection $projects): Collection
    {
        $from = $projects->min('start_date')->copy()->startOfMonth();
        $to = $projects->max('end_date')->copy()->startOfMonth();
        $months = collect();
        for ($m = $from->copy(); $m <= $to; $m->addMonth()) {
            $months->push($m->copy());
        }

        return $months;
    }

    /** Posición (0–100 %) de una fecha dentro del rango del cronograma. */
    public function position(Carbon $date, Collection $months): float
    {
        $start = $months->first();
        $end = $months->last()->copy()->endOfMonth();
        $total = max(1, $start->diffInDays($end));

        return round(max(0, min(100, $start->diffInDays($date, false) / $total * 100)), 2);
    }
}
