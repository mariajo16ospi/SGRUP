<?php

namespace App\Http\Controllers\Vic;

use App\Http\Controllers\Controller;
use App\Support\ApprovalInbox;
use App\Support\DemoData;
use App\Support\FinanceStore;
use App\Support\ProjectTracking;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request, ApprovalInbox $inbox, FinanceStore $finance, ProjectTracking $tracking)
    {
        $all = $inbox->pending();
        $filter = $this->filter($request);

        $tabs = collect(ApprovalInbox::TYPES)->map(fn ($t, $key) => ['key' => $key, 'label' => $t[2], 'count' => $all->where('type', $key)->count()]);
        $transfers = $all->where('type', 'traslado');

        // «Revisar» un proyecto propuesto: modal con su detalle
        $review = null;
        $project = DemoData::get()->projects->firstWhere('id', (int) ($request->query('revisar') ?: old('project_id')));
        if ($project && $project->status === 'propuesto') {
            $review = [
                'project' => $project,
                'lines' => $project->budgetLines->map(fn ($l) => $finance->line($l))->values(),
                'products' => $tracking->products($project),
                'rejecting' => $request->query('accion') === 'rechazar' || old('_form') === 'rechazar-proyecto',
            ];
        }

        return view('vic.approvals', [
            'items' => $filter ? $all->where('type', $filter)->values() : $all,
            'total' => $all->count(),
            'filter' => $filter,
            'tabs' => $tabs,
            'purchases' => $all->where('type', 'compra'),
            'progress' => $all->where('type', 'avance'),
            'transfers' => $transfers,
            'transferSummary' => $transfers->count() === 1
                ? preg_replace('/^\S+ · /', '', $transfers->first()['title']) . ' · ' . money_m($transfers->first()['value'])
                : money($transfers->sum('value')) . ' en total',
            'overdue' => $all->where('overdue', true)->count(),
            'review' => $review,
        ]);
    }

    /** Exportación de la bandeja (respeta el filtro por tipo). */
    public function export(Request $request, ApprovalInbox $inbox)
    {
        $filter = $this->filter($request);
        $items = $inbox->pending()->when($filter, fn ($c) => $c->where('type', $filter));

        return csv_download('aprobaciones-pendientes', ['Tipo', 'Solicitud', 'Detalle', 'Proyecto', 'Solicitante', 'Valor', 'Recibida', 'Días', 'Vencida'],
            $items->map(fn ($i) => [$i['label'], $i['title'], trim(($i['subtitle'] ?? '') . ' ' . ($i['flag'] ?? '')), $i['project']?->name ?? '—',
                $i['requester'], (int) $i['value'], $i['received']->toDateString(),
                (int) $i['received']->copy()->startOfDay()->diffInDays(today()), $i['overdue'] ? 'Sí' : 'No']));
    }

    public function approve(Request $request, string $type, int $id, ApprovalInbox $inbox)
    {
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:500']])['comment'] ?? null;
        $message = $inbox->approve($type, $id, $comment);

        // Desde un modal se vuelve a la página sin el modal abierto
        return redirect($request->input('back') ?: url()->previous())->with('status', $message);
    }

    public function rejectProject(Request $request, int $id, ApprovalInbox $inbox)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], ['reason.required' => 'Escribe el motivo del rechazo.']);

        return redirect()->route('vic.aprobaciones')->with('status', $inbox->rejectProject($id, $data['reason']));
    }

    private function filter(Request $request): ?string
    {
        return array_key_exists($request->query('tipo'), ApprovalInbox::TYPES) ? $request->query('tipo') : null;
    }
}
