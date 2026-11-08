<?php

namespace App\Http\Controllers\Vic;

use App\Http\Controllers\Controller;
use App\Support\ApprovalInbox;
use App\Support\FinanceStore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** VIC 13 · Gestión de compras: aprobación por roles con validación de presupuesto. */
class PurchaseController extends Controller
{
    public const TABS = [
        'todas' => ['Todas', null],
        'pendientes' => ['Pendientes', ['en_vicerrectoria', 'en_financiera', 'requiere_ajuste', 'devuelta']],
        'aprobadas' => ['Aprobadas', ['aprobada', 'pagada']],
        'rechazadas' => ['Rechazadas', ['rechazada']],
    ];

    public function __construct(private FinanceStore $finance) {}

    public function index(Request $request)
    {
        $all = $this->finance->purchases();
        $tab = array_key_exists($request->query('estado'), self::TABS) ? $request->query('estado') : 'todas';
        $statuses = self::TABS[$tab][1];
        $mine = $all->where('status', 'en_vicerrectoria');
        $selected = $request->query('ver')
            ? $this->finance->purchase($request->query('ver'))
            : ($mine->sortBy('submitted_at')->first() ?? $all->first());
        $line = $this->finance->line($selected->budgetLine);
        $month = $all->max('submitted_at')->copy()->startOfMonth();
        $ofMonth = $all->filter(fn ($p) => $p->submitted_at->isSameMonth($month));
        $approved = $all->whereIn('status', ['aprobada', 'pagada']);
        $modal = in_array($request->query('modal'), ['aprobar', 'rechazar', 'ajuste'], true) && $selected->status === 'en_vicerrectoria'
            ? $request->query('modal') : null;

        return view('vic.purchases', [
            'list' => $statuses ? $all->whereIn('status', $statuses)->values() : $all,
            'tab' => $tab,
            'tabs' => collect(self::TABS)->map(fn ($t) => ['label' => $t[0], 'count' => $t[1] ? $all->whereIn('status', $t[1])->count() : $all->count()]),
            'selected' => $selected,
            'line' => $line,
            'insufficient' => $selected->status === 'en_vicerrectoria' && $line['available'] < (float) $selected->value,
            'month' => $month,
            'ofMonth' => $ofMonth,
            'pendingOfMonth' => $ofMonth->whereIn('status', self::TABS['pendientes'][1])->count(),
            'approved' => $approved,
            'mine' => $mine->count(),
            'modal' => $modal,
        ]);
    }

    public function decide(Request $request, string $code, ApprovalInbox $inbox)
    {
        $decision = $request->input('decision');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['rechazar', 'ajuste'])],
            'reason' => ['required', Rule::in($decision === 'rechazar' ? ApprovalInbox::REJECT_REASONS : ApprovalInbox::ADJUST_REASONS)],
            'notes' => ['required', 'string', 'max:500'],
        ], ['notes.required' => $decision === 'rechazar' ? 'Escribe la justificación del rechazo.' : 'Escribe las observaciones para el investigador.']);

        return redirect()->route('vic.compras', ['ver' => $code])
            ->with('status', $inbox->decidePurchase($code, $data['decision'], $data['reason'], $data['notes']));
    }
}
