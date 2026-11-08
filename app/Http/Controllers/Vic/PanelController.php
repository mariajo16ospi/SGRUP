<?php

namespace App\Http\Controllers\Vic;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\DemoData;
use App\Support\SCurve;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    public function __invoke(Request $request)
    {
        $d = DemoData::get();
        $active = $d->projects->where('status', 'activo')->values();
        $type = in_array($request->query('tipo'), ['investigacion', 'extension'], true) ? $request->query('tipo') : null;
        $alerts = $d->alerts->where('resolved', false);

        return view('vic.panel', [
            'active' => $active,
            'list' => $type ? $active->where('type', $type)->values() : $active,
            'type' => $type,
            'total' => $active->sum('value'),
            'executed' => $active->sum('executed_amount'),
            'avgTechnical' => (int) round($active->avg('technical_progress') ?? 0),
            'byPhase' => collect(Project::PHASES)->map(fn ($label, $key) => ['label' => $label, 'count' => $active->where('phase', $key)->count()]),
            'byType' => ['extension' => $active->where('type', 'extension')->count(), 'investigacion' => $active->where('type', 'investigacion')->count()],
            'alertCount' => $alerts->count(),
            'criticalCount' => $alerts->where('severity', 'critica')->count(),
            'actionCount' => $alerts->where('requires_action', true)->count(),
            'recentAlerts' => $alerts->where('severity', 'critica')->take(4),
            'curve' => $active->isNotEmpty() ? SCurve::consolidated($active) : null,
            'purchases' => $d->purchases->whereIn('status', ['en_vicerrectoria', 'en_financiera', 'aprobada'])->sortByDesc('code')->take(5),
        ]);
    }
}
