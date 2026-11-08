<?php

namespace App\Http\Controllers\Vic;

use App\Http\Controllers\Controller;
use App\Support\ApprovalInbox;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function index(Request $request, ApprovalInbox $inbox)
    {
        $all = $inbox->pending();
        $filter = array_key_exists($request->query('tipo'), ApprovalInbox::TYPES) ? $request->query('tipo') : null;

        $tabs = collect(ApprovalInbox::TYPES)->map(fn ($t, $key) => ['key' => $key, 'label' => $t[2], 'count' => $all->where('type', $key)->count()]);
        $transfers = $all->where('type', 'traslado');

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
        ]);
    }

    public function approve(Request $request, string $type, int $id, ApprovalInbox $inbox)
    {
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:500']])['comment'] ?? null;
        $message = $inbox->approve($type, $id, $comment);

        // Desde un modal se vuelve a la página sin el modal abierto
        return redirect($request->input('back') ?: url()->previous())->with('status', $message);
    }
}
