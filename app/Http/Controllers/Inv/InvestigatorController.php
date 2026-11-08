<?php

namespace App\Http\Controllers\Inv;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Vic\MonitoringController;
use App\Http\Controllers\Vic\PurchaseController;
use App\Models\Project;
use App\Support\DemoData;
use App\Support\FinanceStore;
use App\Support\ProjectTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Portal del investigador (mockups INV 01–12). Solo ve los proyectos a su cargo. */
class InvestigatorController extends Controller
{
    public function __construct(private FinanceStore $finance, private ProjectTracking $tracking) {}

    /** Proyectos coordinados por el usuario (activos primero). */
    private function myProjects(bool $withClosed = true): Collection
    {
        return DemoData::get()->projects
            ->where('coordinator_id', auth()->id())
            ->whereIn('status', $withClosed ? ['activo', 'liquidado'] : ['activo'])
            ->sortBy(fn ($p) => [$p->status === 'activo' ? 0 : 1, $p->end_date])->values();
    }

    private function myPurchases(): Collection
    {
        $ids = $this->myProjects()->pluck('id')->all();

        return $this->finance->purchases()->filter(fn ($p) => in_array($p->project_id, $ids, true) || $p->requester_id === auth()->id())->values();
    }

    // ---------- INV 01 · Mis proyectos ----------

    public function home()
    {
        $active = $this->myProjects(false);
        $reports = $active->map(fn ($p) => ($r = $this->tracking->nextReport($p)) ? $r + ['project' => $p] : null)->filter()->sortBy('due')->values();
        $purchases = $this->myPurchases();
        $inProcess = $purchases->whereIn('status', ['en_vicerrectoria', 'en_financiera', 'aprobada', 'requiere_ajuste']);
        $returned = $purchases->where('status', 'requiere_ajuste');
        $products = $active->flatMap(fn ($p) => $this->tracking->products($p));
        $overdue = $products->where('status', 'vencido')->values();
        $docs = $this->finance->documents();

        // Mis pendientes
        $todo = collect();
        foreach ($reports as $r) {
            $todo->push(['title' => 'Cargar el avance de ' . Str::before(month_long($r['period']), ' '), 'detail' => "{$r['project']->name} · vence el " . date_es($r['due']),
                'badge' => $r['days'] < 0 ? 'Vencido' : ($r['days'] === 0 ? 'Vence hoy' : "Vence en {$r['days']} " . ($r['days'] === 1 ? 'día' : 'días')),
                'tone' => $r['days'] <= 3 ? 'amber' : 'gray', 'action' => 'Cargar avance', 'url' => route('inv.proyectos.avances', [$r['project'], 'cargar' => 1])]);
        }
        foreach ($returned as $c) {
            $todo->push(['title' => "Ajustar la solicitud {$c->code}", 'detail' => collect($c->changes_log)->last()['text'] ?? 'Vicerrectoría pidió ajustes',
                'badge' => 'Devuelta', 'tone' => 'red', 'action' => 'Ver solicitud', 'url' => route('inv.compras', ['ver' => $c->code])]);
        }
        foreach ($active as $p) {
            if ($docs->where('project.id', $p->id)->where('type', 'Informe técnico')->isEmpty() && $p->curvePoints->whereNotNull('executed_cumulative')->isNotEmpty()) {
                $todo->push(['title' => 'Subir el informe técnico del periodo', 'detail' => $p->name, 'badge' => 'Documento faltante', 'tone' => 'amber',
                    'action' => 'Subir documento', 'url' => route('inv.proyectos.documentos', [$p, 'modal' => 'subir'])]);
            }
        }
        foreach ($overdue as $o) {
            $todo->push(['title' => 'Entrega vencida: ' . Str::lcfirst($o['name']), 'detail' => "{$o['project']->name} · venció el " . date_es($o['due']),
                'badge' => 'Vencida', 'tone' => 'red', 'action' => 'Ver producto', 'url' => route('inv.proyectos.objetivos', $o['project'])]);
        }

        return view('inv.home', [
            'active' => $active,
            'cards' => $active->map(fn ($p) => ['project' => $p, 'technical' => $this->tracking->compliance($p), 'financial' => $this->finance->projectTotals($p)['executed_pct']]),
            'nextReport' => $reports->first(),
            'inProcess' => $inProcess,
            'returned' => $returned,
            'overdue' => $overdue,
            'todo' => $todo,
        ]);
    }

    // ---------- INV 07 · Mis compras ----------

    public function purchases(Request $request)
    {
        $all = $this->myPurchases();
        $tabs = ['todas' => ['Mis solicitudes', null]] + array_slice(PurchaseController::TABS, 1, null, true);
        $tab = array_key_exists($request->query('estado'), $tabs) ? $request->query('estado') : 'todas';
        $statuses = $tabs[$tab][1];
        $returned = $all->where('status', 'requiere_ajuste');
        $selected = $request->query('ver') ? ($all->firstWhere('code', $request->query('ver')) ?? abort(404)) : ($returned->first() ?? $all->first());
        $month = $all->max('submitted_at')?->copy()->startOfMonth() ?? today()->startOfMonth();
        $ofMonth = $all->filter(fn ($p) => $p->submitted_at->isSameMonth($month));
        $modal = $request->query('modal');
        $projects = $this->myProjects(false);

        return view('inv.purchases', [
            'list' => $statuses ? $all->whereIn('status', $statuses)->values() : $all,
            'tab' => $tab,
            'tabs' => collect($tabs)->map(fn ($t) => ['label' => $t[0], 'count' => $t[1] ? $all->whereIn('status', $t[1])->count() : $all->count()]),
            'selected' => $selected,
            'line' => $selected ? $this->finance->line($selected->budgetLine) : null,
            'month' => $month,
            'ofMonth' => $ofMonth,
            'pendingOfMonth' => $ofMonth->whereIn('status', PurchaseController::TABS['pendientes'][1])->count(),
            'approved' => $all->whereIn('status', ['aprobada', 'pagada']),
            'returned' => $returned,
            'projects' => $projects,
            'lineData' => $projects->mapWithKeys(fn ($p) => [$p->id => $p->budgetLines->map(fn ($l) => $this->finance->line($l))->values()]),
            'modal' => in_array($modal, ['nueva', 'editar'], true) || old('_form') ? ($modal ?? old('_form')) : null,
            'defaultProject' => $request->integer('proyecto') ?: $projects->first()?->id,
        ]);
    }

    private function validatePurchase(Request $request, Collection $projects): array
    {
        $project = $projects->firstWhere('id', $request->integer('project_id')) ?? abort(403, 'Este proyecto no está a tu cargo.');

        return [$project, $request->validate([
            'project_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:160'],
            'original_value' => ['nullable', 'numeric', 'min:0'],
            'line' => ['required', Rule::in($project->budgetLines->keys()->all())],
            'value' => ['required', 'numeric', 'min:10000'],
            'justification' => ['required', 'string', 'max:500'],
            'quotes.*' => ['nullable', 'file', 'max:10240'],
        ], [
            'description.required' => 'Indica el bien o servicio a adquirir.',
            'value.required' => 'Indica el valor a solicitar.',
            'justification.required' => 'Explica para qué producto del proyecto se necesita.',
        ])];
    }

    public function storePurchase(Request $request)
    {
        [$project, $data] = $this->validatePurchase($request, $this->myProjects(false));
        $message = $this->finance->createPurchase($project, $data);

        return redirect()->route('inv.compras', ['ver' => $this->finance->purchases()->sortByDesc('id')->first()->code])->with('status', $message);
    }

    /** «Editar y reenviar» una solicitud devuelta para ajuste. */
    public function resubmit(Request $request, string $code)
    {
        $p = $this->myPurchases()->firstWhere('code', $code) ?? abort(404);
        abort_unless($p->status === 'requiere_ajuste', 409, 'Solo se pueden reenviar solicitudes devueltas para ajuste.');
        $request->merge(['project_id' => $p->project_id, 'line' => $p->budgetLine->name]);
        [, $data] = $this->validatePurchase($request, $this->myProjects(false));
        $this->finance->update($code, [
            'status' => 'en_vicerrectoria', 'value' => (float) $data['value'], 'description' => $data['description'],
            'original_value' => (float) ($data['original_value'] ?: $p->original_value ?: $data['value']),
        ], 'Editó y reenvió la solicitud: ' . $data['justification']);

        return redirect()->route('inv.compras', ['ver' => $code])->with('status', "{$code} reenviada a Vicerrectoría.");
    }

    public function withdraw(string $code)
    {
        $p = $this->myPurchases()->firstWhere('code', $code) ?? abort(404);
        abort_unless(in_array($p->status, ['en_vicerrectoria', 'requiere_ajuste'], true), 409, 'Esta solicitud ya no se puede retirar.');
        $this->finance->update($code, ['status' => 'retirada'], 'Retiró la solicitud');

        return redirect()->route('inv.compras', ['ver' => $code])->with('status', "{$code} retirada.");
    }

    // ---------- INV 08 · Mis documentos ----------

    public function documents(Request $request)
    {
        $ids = $this->myProjects()->pluck('id')->all();
        $all = $this->finance->documents()->filter(fn ($d) => in_array($d['project']->id, $ids, true))->values();
        $cat = array_key_exists($request->query('tipo'), FinanceStore::DOC_CATEGORIES) ? $request->query('tipo') : null;
        $q = trim((string) $request->query('q'));
        $needle = Str::lower(Str::ascii($q));
        $list = $all->filter(fn ($d) => ! $cat || $d['category'] === $cat)
            ->filter(fn ($d) => $needle === '' || Str::contains(Str::lower(Str::ascii("{$d['name']} {$d['type']} {$d['project']->name}")), $needle))->values();

        return view('fin.documents', [
            'area' => 'inv',
            'all' => $all,
            'list' => $request->boolean('todos') ? $list : $list->take(8),
            'filtered' => $list->count(),
            'cat' => $cat,
            'q' => $q,
            'rup' => $this->finance->rupStatus()->filter(fn ($r) => in_array($r['project']->id, $ids, true))->values(),
            'projects' => $this->myProjects(),
            'modal' => $request->query('modal') === 'subir' || old('_form') === 'subir',
        ]);
    }

    public function upload(Request $request)
    {
        $project = $this->myProjects()->firstWhere('id', $request->integer('project_id')) ?? abort(403, 'Este proyecto no está a tu cargo.');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(FinanceStore::DOC_TYPES))],
            'file' => ['required', 'file', 'max:1536', 'mimes:pdf,docx,xlsx,zip,png,jpg,jpeg'],
        ], ['file.required' => 'Selecciona un archivo.', 'file.max' => 'En esta demo el archivo puede pesar hasta 1,5 MB.', 'file.mimes' => 'Formatos permitidos: PDF, DOCX, XLSX, ZIP o imágenes.']);

        return redirect()->route('inv.documentos')->with('status', $this->finance->upload($project, $data['type'], $request->file('file')));
    }

    public function rupPackage()
    {
        $ids = $this->myProjects()->pluck('id')->all();
        $docs = $this->finance->documents()->whereIn('type', FinanceStore::RUP_DOCS)->filter(fn ($d) => in_array($d['project']->id, $ids, true));

        return csv_download('paquete-camara-de-comercio', ['Proyecto', 'Código', 'Entidad', 'Documento', 'Archivo', 'Fecha', 'Estado'],
            $docs->map(fn ($d) => [$d['project']->name, $d['project']->code, $d['project']->entity, $d['type'], $d['name'], $d['date']->toDateString(), $d['validated'] ? 'Validado' : 'En revisión']));
    }

    // ---------- INV 09 · Cronograma y INV 10 · Alertas ----------

    public function schedule(Request $request)
    {
        $withClosed = $request->boolean('liquidados');
        $projects = $this->myProjects($withClosed)->sortBy('start_date')->values();

        return view('vic.schedule', app(MonitoringController::class)->scheduleData($projects, $withClosed) + ['area' => 'inv']);
    }

    public function alerts(Request $request)
    {
        $ids = $this->myProjects()->pluck('id')->all();
        $all = DemoData::get()->alerts->where('resolved', false)->filter(fn ($a) => in_array($a->project_id, $ids, true))->values();
        $level = array_key_exists($request->query('nivel'), MonitoringController::ALERT_TABS) ? $request->query('nivel') : null;

        return view('vic.alerts', ['area' => 'inv', 'all' => $all, 'list' => $level ? $all->where('severity', $level)->values() : $all, 'level' => $level]);
    }

    /** «Cargar avance» del menú: abre el modal en el proyecto con el reporte más próximo a vencer. */
    public function loadReport()
    {
        $next = $this->myProjects(false)->map(fn ($p) => ($r = $this->tracking->nextReport($p)) ? $r + ['project' => $p] : null)->filter()->sortBy('due')->first();
        if (! $next) {
            return redirect()->route('inv.home')->with('status', 'Estás al día: no tienes avances pendientes por cargar.');
        }

        return redirect()->route('inv.proyectos.avances', [$next['project'], 'cargar' => 1]);
    }
}
