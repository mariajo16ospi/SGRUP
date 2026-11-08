<?php

namespace App\Http\Controllers\Fin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\DemoData;
use App\Support\FinanceStore;
use App\Support\SCurve;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Vista del Área financiera (mockups FIN 01–06). */
class FinanceController extends Controller
{
    public function __construct(private FinanceStore $store) {}

    // ---------- FIN 01 · Control financiero ----------

    public function control()
    {
        $items = $this->store->toManage();
        $active = $this->store->activeProjects();
        $totals = $active->map(fn ($p) => $this->store->projectTotals($p));
        $overruns = $this->store->overruns();

        return view('fin.control', [
            'items' => $items,
            'cdp' => $items->where('kind', 'cdp'),
            'payments' => $items->whereIn('kind', ['pago', 'cuota']),
            'executed' => $totals->sum('executed'),
            'budget' => $totals->sum('value'),
            'totals' => $totals,
            'overrunProjects' => $overruns->pluck('project')->unique('id')->values(),
        ]);
    }

    public function exportControl()
    {
        return $this->csv('por-gestionar', ['Código', 'Fecha', 'Concepto', 'Proyecto', 'Rubro', 'Valor', 'Estado'],
            $this->store->toManage()->map(fn ($i) => [$i['label'] ?? $i['code'], $i['date']->toDateString(), $i['concept'], $i['project']->name, $i['line'], (int) $i['value'], $i['state']]));
    }

    // ---------- FIN 02 · Validación de compras ----------

    public const PURCHASE_TABS = [
        'todas' => ['Todas', null],
        'pendientes' => ['Pendientes', ['en_vicerrectoria', 'en_financiera', 'requiere_ajuste', 'devuelta']],
        'aprobadas' => ['Aprobadas', ['aprobada', 'pagada']],
        'rechazadas' => ['Rechazadas', ['rechazada']],
    ];

    public function purchases(Request $request)
    {
        $all = $this->store->purchases();
        $tab = array_key_exists($request->query('estado'), self::PURCHASE_TABS) ? $request->query('estado') : 'todas';
        $statuses = self::PURCHASE_TABS[$tab][1];
        $list = $statuses ? $all->whereIn('status', $statuses)->values() : $all;

        $actionable = $all->filter(fn ($p) => $this->store->needsCdp($p) || $this->store->needsPayment($p));
        $selected = $request->query('ver')
            ? $this->store->purchase($request->query('ver'))
            : ($actionable->sortBy('submitted_at')->first() ?? $all->first());

        $month = $all->max('submitted_at')->copy()->startOfMonth();
        $ofMonth = $all->filter(fn ($p) => $p->submitted_at->isSameMonth($month));
        $approved = $all->whereIn('status', ['aprobada', 'pagada']);
        $modal = in_array($request->query('modal'), ['cdp', 'pago', 'devolver'], true) ? $request->query('modal') : null;
        if ($modal === 'cdp' || $modal === 'devolver') {
            $modal = $this->store->needsCdp($selected) ? $modal : null;
        } elseif ($modal === 'pago') {
            $modal = $this->store->needsPayment($selected) ? $modal : null;
        }

        return view('fin.purchases', [
            'list' => $list,
            'tab' => $tab,
            'tabs' => collect(self::PURCHASE_TABS)->map(fn ($t, $k) => ['label' => $t[0], 'count' => $t[1] ? $all->whereIn('status', $t[1])->count() : $all->count()]),
            'selected' => $selected,
            'line' => $this->store->line($selected->budgetLine),
            'month' => $month,
            'ofMonth' => $ofMonth,
            'pendingOfMonth' => $ofMonth->whereIn('status', self::PURCHASE_TABS['pendientes'][1])->count(),
            'approved' => $approved,
            'actionable' => $actionable->count(),
            'flow' => $this->flow($selected),
            'history' => $this->history($selected),
            'modal' => $modal,
            'store' => $this->store,
        ]);
    }

    /** Los 4 pasos del flujo de aprobación con su estado. */
    private function flow($p): array
    {
        $vicDone = ! in_array($p->status, ['en_vicerrectoria', 'requiere_ajuste'], true);
        $rejected = $p->status === 'rechazada';
        $finDone = $p->cdp || $p->status === 'pagada';
        $paid = $p->status === 'pagada';
        $approval = $p->submitted_at->copy()->addDays(6);

        return [
            ['Investigador / Coordinador', 'Creó la solicitud · ' . $p->requester->name . ' · ' . date_es($p->submitted_at), 'done'],
            ['Vicerrectoría / Subdirección', match (true) {
                $rejected => 'Rechazó · Dra. Marcela Trujillo',
                $p->status === 'requiere_ajuste' => 'Devolvió para ajuste · el valor supera lo cotizado',
                $vicDone => 'Aprobó · Dra. Marcela Trujillo · ' . date_es($approval),
                default => 'En revisión técnica',
            }, $rejected ? 'error' : ($vicDone ? 'done' : 'current')],
            ['Área financiera', match (true) {
                $p->status === 'devuelta' => 'Devuelta a Vicerrectoría',
                $finDone => 'CDP ' . $p->cdp . ' expedido',
                $vicDone && ! $rejected => 'Te corresponde: expedir CDP',
                default => 'Valida presupuesto y expide CDP',
            }, $finDone ? 'done' : ($vicDone && ! $rejected && $p->status !== 'devuelta' ? 'current' : 'next')],
            ['Pago', $paid ? 'Pago registrado' : ($finDone ? 'Te corresponde: registrar el pago' : 'Registro del gasto y soporte'), $paid ? 'done' : ($finDone ? 'current' : 'next')],
        ];
    }

    private function history($p): array
    {
        $rows = [['by' => $p->requester->name, 'at' => $p->submitted_at, 'text' => 'Creó la solicitud', 'note' => null]];
        if (! in_array($p->status, ['en_vicerrectoria'], true)) {
            $rows[] = ['by' => 'Dra. Marcela Trujillo', 'at' => $p->submitted_at->copy()->addDays(6), 'text' => match ($p->status) {
                'rechazada' => 'Rechazó la solicitud', 'requiere_ajuste' => 'Devolvió para ajuste', default => 'Aprobó la solicitud',
            }, 'note' => match ($p->status) {
                'rechazada' => 'Fuera del alcance del convenio.',
                'requiere_ajuste' => 'Adjuntar cotizaciones adicionales o solicitar traslado presupuestal.',
                default => 'Aprobada por Vicerrectoría. Proceder con el CDP.',
            }];
        }
        foreach ($p->changes_log as $log) {
            $rows[] = ['by' => $log['by'], 'at' => \Illuminate\Support\Carbon::parse($log['at']), 'text' => $log['text'], 'note' => null];
        }

        return $rows;
    }

    public function issueCdp(Request $request, string $code)
    {
        $data = $request->validate([
            'cdp_number' => ['required', 'regex:/^CDP-\d{4}-\d{4}$/'],
            'cdp_date' => ['required', 'date'],
            'source' => ['required', 'string', 'max:160'],
        ], ['cdp_number.regex' => 'El número debe tener el formato CDP-2026-0000.', 'source.required' => 'Indica la fuente de financiación.']);

        return redirect()->route('fin.compras', ['ver' => $code])->with('status', $this->store->issueCdp($code, $data));
    }

    public function returnPurchase(Request $request, string $code)
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(FinanceStore::RETURN_REASONS)],
            'notes' => ['required', 'string', 'max:500'],
        ], ['notes.required' => 'Escribe las observaciones para Vicerrectoría.']);

        return redirect()->route('fin.compras', ['ver' => $code])->with('status', $this->store->returnPurchase($code, $data));
    }

    public function registerPayment(Request $request, string $code)
    {
        $isInstallment = str_starts_with($code, 'CUOTA-');
        $max = $isInstallment
            ? ($this->store->installments()->firstWhere('key', $code)['value'] ?? abort(404))
            : (float) $this->store->purchase($code)->value;
        $data = $request->validate([
            'supplier' => [$isInstallment ? 'nullable' : 'required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:1', 'max:' . $max],
            'date' => ['required', 'date'],
            'method' => ['required', Rule::in(FinanceStore::PAYMENT_METHODS)],
            'voucher' => ['required', 'string', 'max:30'],
            'files.*' => ['nullable', 'file', 'max:10240'],
        ], [
            'supplier.required' => 'Indica el proveedor.',
            'amount.max' => 'El valor pagado no puede superar el valor comprometido (' . money($max) . ').',
            'voucher.required' => 'Indica el número de comprobante de egreso.',
        ]);

        if ($isInstallment) {
            return redirect()->route('fin.control')->with('status', $this->store->payInstallment($code, $data));
        }
        $back = $request->input('back') === 'control' ? route('fin.control') : route('fin.compras', ['ver' => $code]);

        return redirect($back)->with('status', $this->store->registerPayment($code, $data));
    }

    /** Modal de pago abierto desde FIN 01 (compras con CDP o cuotas del contrato). */
    public function paymentForm(string $code)
    {
        if (str_starts_with($code, 'CUOTA-')) {
            $i = $this->store->installments()->firstWhere('key', $code) ?? abort(404);
            abort_if($i['paid'], 409);
            $item = ['code' => $code, 'title' => "{$i['label']} · {$i['concept']}", 'subtitle' => $i['project']->name,
                'cdp' => null, 'value' => $i['value'], 'supplier' => $i['project']->entity];
        } else {
            $p = $this->store->purchase($code);
            abort_unless($this->store->needsPayment($p), 409);
            $item = ['code' => $code, 'title' => $p->description, 'subtitle' => $p->project->name, 'cdp' => $p->cdp,
                'value' => (float) $p->value, 'supplier' => $this->store->supplier($code)];
        }

        return $this->control()->with(['payItem' => $item, 'voucher' => $this->store->nextVoucherNumber()]);
    }

    // ---------- FIN 03 · Control presupuestario ----------

    public function budget()
    {
        $active = $this->store->activeProjects();
        $totals = $active->map(fn ($p) => $this->store->projectTotals($p));

        return view('fin.budget', [
            'totals' => $totals,
            'budget' => $totals->sum('value'),
            'executed' => $totals->sum('executed'),
            'planned' => $totals->sum('planned'),
            'committed' => $totals->sum('committed'),
            'curve' => SCurve::consolidated($active),
            'byLine' => $this->store->byLine(),
            'overruns' => $this->store->overruns(),
        ]);
    }

    public function exportBudget()
    {
        return $this->csv('control-presupuestario', ['Proyecto', 'Presupuesto', 'Planeado a la fecha', 'Ejecutado', 'Comprometido', 'Disponible', 'Ejecución %'],
            $this->store->activeProjects()->map(function ($p) {
                $t = $this->store->projectTotals($p);

                return [$p->name, (int) $t['value'], (int) $t['planned'], (int) $t['executed'], (int) $t['committed'], (int) $t['available'], $t['executed_pct']];
            }));
    }

    // ---------- FIN 04 · Presupuesto por rubros ----------

    public function projectBudget(Request $request, Project $project)
    {
        abort_if($project->status === 'propuesto', 404);

        return view('fin.project', [
            'project' => $project,
            't' => $this->store->projectTotals($project),
            'movements' => $this->store->movements($project),
            'transfers' => $this->store->transfers($project),
            'modal' => $request->query('modal') === 'traslado' && $project->status === 'activo' ? 'traslado' : null,
        ]);
    }

    public function exportMovements(Project $project)
    {
        return $this->csv('movimientos-' . Str::slug($project->code), ['Fecha', 'Rubro', 'Descripción', 'Origen', 'Valor'],
            $this->store->movements($project)->map(fn ($m) => [$m['date']->toDateString(), $m['line'], $m['description'], $m['origin'], (int) $m['value']]));
    }

    public function requestTransfer(Request $request, Project $project)
    {
        abort_unless($project->status === 'activo', 409);
        $lines = $project->budgetLines->keys()->all();
        $data = $request->validate([
            'from' => ['required', Rule::in($lines)],
            'to' => ['required', Rule::in($lines), 'different:from'],
            'amount' => ['required', 'numeric', 'min:100000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'to.different' => 'El rubro de destino debe ser distinto al de origen.',
            'amount.required' => 'Escribe el valor a trasladar.',
            'amount.min' => 'El valor mínimo es $100.000.',
        ]);

        return redirect()->route('fin.proyecto', $project)->with('status', $this->store->requestTransfer($project, $data));
    }

    // ---------- FIN 05 · Soportes y pagos (gestión documental) ----------

    public function documents(Request $request)
    {
        $all = $this->store->documents();
        $cat = array_key_exists($request->query('tipo'), FinanceStore::DOC_CATEGORIES) ? $request->query('tipo') : null;
        $q = trim((string) $request->query('q'));
        $needle = Str::lower(Str::ascii($q));
        $list = $all
            ->filter(fn ($d) => ! $cat || $d['category'] === $cat)
            ->filter(fn ($d) => $needle === '' || Str::contains(Str::lower(Str::ascii("{$d['name']} {$d['type']} {$d['project']->name}")), $needle))
            ->values();

        return view('fin.documents', [
            'all' => $all,
            'list' => $request->boolean('todos') ? $list : $list->take(8),
            'filtered' => $list->count(),
            'cat' => $cat,
            'q' => $q,
            'rup' => $this->store->rupStatus(),
            'projects' => DemoData::get()->projects->except('garzon')->values(),
            'modal' => $request->query('modal') === 'subir' || old('_form') === 'subir',
        ]);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(FinanceStore::DOC_TYPES))],
            'file' => ['required', 'file', 'max:1536', 'mimes:pdf,docx,xlsx,zip,png,jpg,jpeg'],
        ], [
            'file.required' => 'Selecciona un archivo.',
            'file.max' => 'En esta demo el archivo puede pesar hasta 1,5 MB.',
            'file.mimes' => 'Formatos permitidos: PDF, DOCX, XLSX, ZIP o imágenes.',
        ]);

        return redirect()->route('fin.documentos')->with('status', $this->store->upload(DemoData::project($data['project_id']), $data['type'], $request->file('file')));
    }

    public function validateDocument(string $id)
    {
        return back()->with('status', $this->store->validateDocument($id));
    }

    public function download(string $id)
    {
        $doc = $this->store->document($id);
        abort_unless($doc['path'] && Storage::exists($doc['path']), 404, 'Este documento es de ejemplo y no tiene archivo.');

        return Storage::download($doc['path'], $doc['name']);
    }

    /** Paquete Cámara de Comercio: índice de los documentos RUP por proyecto. */
    public function rupPackage()
    {
        $docs = $this->store->documents()->whereIn('type', FinanceStore::RUP_DOCS);

        return $this->csv('paquete-camara-de-comercio', ['Proyecto', 'Código', 'Entidad', 'Documento', 'Archivo', 'Fecha', 'Estado'],
            $docs->sortBy(fn ($d) => [$d['project']->id, array_search($d['type'], FinanceStore::RUP_DOCS)])
                ->map(fn ($d) => [$d['project']->name, $d['project']->code, $d['project']->entity, $d['type'], $d['name'], $d['date']->toDateString(), $d['validated'] ? 'Validado' : 'En revisión']));
    }

    // ---------- FIN 06 · Alertas ----------

    public const ALERT_TABS = ['critica' => 'Críticas', 'advertencia' => 'Advertencias', 'informativa' => 'Informativas'];

    public function alerts(Request $request)
    {
        $all = $this->store->alerts();
        $level = array_key_exists($request->query('nivel'), self::ALERT_TABS) ? $request->query('nivel') : null;

        return view('fin.alerts', [
            'all' => $all,
            'list' => $level ? $all->where('severity', $level)->values() : $all,
            'level' => $level,
        ]);
    }

    // ---------- Utilidades ----------

    private function csv(string $name, array $header, iterable $rows)
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, "{$name}-" . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
