<?php

namespace App\Http\Controllers\Vic;

use App\Http\Controllers\Controller;
use App\Support\DemoData;
use App\Support\FinanceStore;
use App\Support\ProjectTracking;
use Illuminate\Http\Request;

/** Cronograma, seguimiento técnico, reportes y alertas (mockups VIC 11, 12, 14 y 15). */
class MonitoringController extends Controller
{
    public function __construct(private ProjectTracking $tracking, private FinanceStore $finance) {}

    // ---------- VIC 11 · Cronograma ----------

    public function schedule(Request $request)
    {
        $withClosed = $request->boolean('liquidados');
        $projects = DemoData::get()->projects
            ->whereIn('status', $withClosed ? ['activo', 'liquidado'] : ['activo'])
            ->sortBy('start_date')->values();

        return view('vic.schedule', $this->scheduleData($projects, $withClosed) + ['area' => 'vic']);
    }

    /** Filas del Gantt: fases y hitos de cada proyecto en posiciones porcentuales. */
    public function scheduleData(\Illuminate\Support\Collection $projects, bool $withClosed): array
    {
        $months = $this->tracking->months($projects);
        $pos = fn ($date) => $this->tracking->position($date, $months);

        $rows = $projects->map(fn ($p) => [
            'project' => $p,
            'phases' => collect($p->phaseRoute())->filter(fn ($ph) => $ph['from'] && $ph['to'])->map(fn ($ph) => [
                'key' => $ph['key'], 'label' => $ph['label'], 'left' => $pos($ph['from']),
                'width' => max(0.5, $pos($ph['to']->copy()->addDay()) - $pos($ph['from'])),
                'title' => "{$ph['label']}: " . date_es($ph['from']) . ' – ' . date_es($ph['to']),
            ])->values(),
            'milestones' => $this->tracking->products($p)->map(fn ($m) => [
                'left' => $pos($m['due']), 'state' => $m['status'] === 'vencido' ? 'late' : ($m['status'] === 'entregado' ? 'done' : 'pending'),
                'title' => "{$m['name']} · " . date_es($m['due']) . " · {$m['progress']} % · {$m['status_label']}",
            ]),
        ]);

        return ['rows' => $rows, 'months' => $months, 'today' => $pos(today()), 'withClosed' => $withClosed];
    }

    // ---------- VIC 12 · Seguimiento técnico ----------

    public const PRODUCT_FILTERS = ['vencidos' => ['Vencidos', 'vencido'], 'proximos' => ['Próximos 30 días', 'proximo'], 'entregados' => ['Entregados', 'entregado']];

    public function tracking(Request $request)
    {
        $active = DemoData::get()->projects->where('status', 'activo')->values();
        $all = $this->tracking->products()->filter(fn ($p) => $p['project']->status === 'activo')->values();
        $filter = array_key_exists($request->query('filtro'), self::PRODUCT_FILTERS) ? $request->query('filtro') : null;

        return view('vic.tracking', [
            'all' => $all,
            'list' => $filter ? $all->where('status', self::PRODUCT_FILTERS[$filter][1])->values() : $all,
            'filter' => $filter,
            'projects' => $active->map(fn ($p) => ['project' => $p, 'progress' => $this->tracking->compliance($p), 'time' => $p->timeElapsedPercent()]),
        ]);
    }

    // ---------- VIC 14 · Reportes y experiencia ----------

    public function reports()
    {
        $projects = DemoData::get()->projects->whereIn('status', ['activo', 'liquidado'])->sortBy(fn ($p) => [$p->status === 'activo' ? 0 : 1, $p->id])->values();

        return view('vic.reports', [
            'projects' => $projects,
            'byEntity' => $projects->groupBy('entity')->map(fn ($g) => $g->sum('value'))->sortDesc(),
            'total' => $projects->sum('value'),
            'liquidated' => $projects->where('status', 'liquidado')->count(),
        ]);
    }

    public function exportReport(string $report)
    {
        $d = DemoData::get();
        $projects = $d->projects->whereIn('status', ['activo', 'liquidado'])->values();

        return match ($report) {
            'consolidado' => csv_download('consolidado-proyectos', ['Código', 'Proyecto', 'Tipo', 'Entidad', 'Coordinador', 'Fase', 'Estado', 'Valor', 'Ejecutado', 'Ejecución %', 'Avance técnico %', 'Tiempo transcurrido %'],
                $projects->map(fn ($p) => [$p->code, $p->name, $p->typeLabel(), $p->entity, $p->coordinator->name, $p->phaseLabel(), $p->statusLabel(),
                    (int) $p->value, (int) $p->executed_amount, $p->financialProgress(), $this->tracking->compliance($p), $p->timeElapsedPercent()])),
            'rubros' => csv_download('ejecucion-por-rubro', ['Proyecto', 'Rubro', 'Asignado', 'Ejecutado', 'Comprometido', 'Disponible'],
                $projects->flatMap(fn ($p) => $p->budgetLines->map(fn ($l) => $this->finance->line($l))
                    ->map(fn ($l) => [$p->name, $l['name'], (int) $l['budgeted'], (int) $l['executed'], (int) $l['committed'], (int) $l['available']]))),
            'compras' => csv_download('trazabilidad-compras', ['Código', 'Fecha', 'Proyecto', 'Rubro', 'Descripción', 'Solicitante', 'Valor', 'Cotizado', 'Estado', 'CDP', 'Historial'],
                $this->finance->purchases()->map(fn ($c) => [$c->code, $c->submitted_at->toDateString(), $c->project->name, $c->budgetLine->name, $c->description,
                    $c->requester->name, (int) $c->value, $c->original_value ? (int) $c->original_value : '', $c->statusLabel(), $c->cdp ?? '',
                    collect($c->changes_log)->map(fn ($l) => "{$l['at']} {$l['by']}: {$l['text']}")->implode(' | ')])),
            'experiencia' => csv_download('experiencia-institucional-rup', ['Código', 'Objeto', 'Contratante', 'Inicio', 'Fin', 'Valor', 'Estado'],
                $projects->map(fn ($p) => [$p->code, $p->name, $p->entity, $p->start_date->toDateString(), $p->end_date->toDateString(), (int) $p->value,
                    $p->status === 'liquidado' ? 'Liquidado' : 'En ejecución'])),
            default => abort(404),
        };
    }

    /** Informe de gestión imprimible (se guarda como PDF desde el navegador). */
    public function printable()
    {
        $active = DemoData::get()->projects->where('status', 'activo')->values();

        return view('vic.print', [
            'totals' => $active->map(fn ($p) => $this->finance->projectTotals($p) + ['technical' => $this->tracking->compliance($p)]),
            'alerts' => DemoData::get()->alerts,
            'products' => $this->tracking->products()->filter(fn ($p) => $p['project']->status === 'activo'),
        ]);
    }

    // ---------- VIC 15 · Alertas ----------

    public const ALERT_TABS = ['critica' => 'Críticas', 'advertencia' => 'Advertencias', 'informativa' => 'Informativas'];

    public function alerts(Request $request)
    {
        $all = DemoData::get()->alerts->where('resolved', false)->values();
        $level = array_key_exists($request->query('nivel'), self::ALERT_TABS) ? $request->query('nivel') : null;

        return view('vic.alerts', ['area' => 'vic', 'all' => $all, 'list' => $level ? $all->where('severity', $level)->values() : $all, 'level' => $level]);
    }
}
