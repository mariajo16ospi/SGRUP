<?php

namespace App\Http\Controllers\Vic;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\ApprovalInbox;
use App\Support\DemoData;
use App\Support\DemoState;
use App\Support\FinanceStore;
use App\Support\ProjectTracking;
use App\Support\SCurve;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Proyectos y contratos y las pestañas de cada proyecto (mockups VIC 03–10). */
class ProjectController extends Controller
{
    public const FILTERS = [
        'todos' => 'Todos',
        'activos' => 'Activos',
        'liquidados' => 'Liquidados',
        'investigacion' => 'Investigación',
        'extension' => 'Extensión',
    ];

    public const TABS = [
        'show' => 'Resumen',
        'objetivos' => 'Objetivos y productos',
        'presupuesto' => 'Presupuesto',
        'avances' => 'Avances mensuales',
        'compras' => 'Compras',
        'documentos' => 'Documentos',
        'trazabilidad' => 'Trazabilidad',
    ];

    public function __construct(private FinanceStore $finance, private ProjectTracking $tracking) {}

    public function index(Request $request)
    {
        $filter = array_key_exists($request->query('filtro'), self::FILTERS) ? $request->query('filtro') : 'todos';
        $q = trim((string) $request->query('q'));

        return view('vic.projects.index', [
            'projects' => $this->filtered($filter, $q),
            'filter' => $filter,
            'q' => $q,
        ]);
    }

    /** Exportación a CSV (se abre directamente en Excel). */
    public function export(Request $request)
    {
        $filter = array_key_exists($request->query('filtro'), self::FILTERS) ? $request->query('filtro') : 'todos';
        $projects = $this->filtered($filter, trim((string) $request->query('q')));

        return csv_download('proyectos-sgrup', ['Código', 'Proyecto', 'Tipo', 'Modalidad', 'Entidad', 'Coordinador', 'Inicio', 'Fin', 'Valor', 'Ejecutado', 'Avance técnico %', 'Ejecución %', 'Fase', 'Estado'],
            $projects->map(fn ($p) => [$p->code, $p->name, $p->typeLabel(), $p->modalityLabel(), $p->entity, $p->coordinator->name,
                $p->start_date->toDateString(), $p->end_date->toDateString(), (int) $p->value, (int) $p->executed_amount,
                $p->technical_progress, $p->financialProgress(), $p->phaseLabel(), $p->statusLabel()]));
    }

    private function filtered(string $filter, string $q): Collection
    {
        $needle = Str::lower(Str::ascii($q));

        return DemoData::get()->projects
            ->whereIn('status', ['activo', 'liquidado'])
            ->filter(fn (Project $p) => match ($filter) {
                'activos' => $p->status === 'activo',
                'liquidados' => $p->status === 'liquidado',
                'investigacion', 'extension' => $p->type === $filter,
                default => true,
            })
            ->filter(fn (Project $p) => $needle === '' || Str::contains(Str::lower(Str::ascii("{$p->name} {$p->code} {$p->entity}")), $needle))
            ->sortBy(fn (Project $p) => [$p->status === 'activo' ? 0 : 1, $p->id])
            ->values();
    }

    /** «vic» o «inv»: las pestañas del proyecto se comparten entre Vicerrectoría e Investigador. */
    private function area(): string
    {
        return request()->routeIs('inv.*') ? 'inv' : 'vic';
    }

    private function route(string $name, mixed $params = []): string
    {
        return route($this->area() . '.proyectos.' . $name, $params);
    }

    /** Datos comunes del encabezado del proyecto. */
    private function base(Project $project, string $tab): array
    {
        abort_if($project->status === 'propuesto', 404);
        $area = $this->area();
        // El investigador solo ve los proyectos a su cargo
        abort_if($area === 'inv' && $project->coordinator_id !== auth()->id(), 403, 'Este proyecto no está a tu cargo.');
        $pending = $this->tracking->progressRequests()->where('project_id', $project->id)->where('status', 'pendiente')
            ->reject(fn ($a) => isset(DemoState::get('vic_progress', [])[$a->id]))->first();

        return ['project' => $project, 'tab' => $tab, 'area' => $area, 'pendingProgress' => $area === 'vic' ? $pending : null,
            'nextReport' => $area === 'inv' && $project->status === 'activo' ? $this->tracking->nextReport($project) : null];
    }

    // ---------- VIC 04 · Resumen y curva S ----------

    public function show(Project $project)
    {
        $curve = SCurve::forProject($project);
        $last = $curve->lastExecuted();

        return view('vic.projects.show', $this->base($project, 'show') + [
            'curve' => $curve,
            'plannedToDate' => $last['planned_amount'] ?? 0,
            'executedToDate' => $last['executed_amount'] ?? 0,
            'plannedPct' => (int) round($last['planned'] ?? 0),
            'alerts' => DemoData::get()->alerts->where('project_id', $project->id)->where('resolved', false)
                ->sortBy(fn ($a) => [$a->severity === 'critica' ? 0 : 1, $a->id])->values(),
        ]);
    }

    public function advancePhase(Project $project)
    {
        $next = $project->nextPhase();
        abort_unless($project->status === 'activo' && $next, 409);
        $this->tracking->log($project, 'Solicitó el cambio de fase a ' . Project::PHASES[$next]);

        return back()->with('status', 'Versión de diseño: el cambio a la fase de ' . Project::PHASES[$next] . ' quedó registrado en la trazabilidad (la fase no cambia en los datos de ejemplo).');
    }

    public function liquidate(Project $project)
    {
        abort_unless($project->status === 'activo', 409);
        $this->tracking->log($project, 'Solicitó la liquidación del proyecto');

        return back()->with('status', "Versión de diseño: la liquidación de «{$project->name}» quedó registrada en la trazabilidad (el proyecto sigue activo en los datos de ejemplo).");
    }

    // ---------- VIC 06 · Objetivos y productos ----------

    public function objectives(Request $request, Project $project)
    {
        $products = $this->tracking->products($project);

        return view('vic.projects.objectives', $this->base($project, 'objetivos') + [
            'products' => $products,
            'compliance' => $this->tracking->compliance($project),
            'byType' => $products->groupBy('type')->map(fn ($g) => (int) round($g->avg('progress'))),
            'modal' => $request->query('modal') === 'producto' || old('_form') === 'producto',
            'updating' => $request->query('actualizar') ? $products->firstWhere('id', (int) $request->query('actualizar')) : null,
        ]);
    }

    /** «Actualizar»: el investigador registra la cantidad acumulada lograda de un producto. */
    public function updateProduct(Request $request, Project $project, int $id)
    {
        $this->base($project, 'objetivos');
        $data = $request->validate(['done' => ['required', 'integer', 'min:0', 'max:100000']], ['done.required' => 'Escribe la cantidad acumulada.']);

        return redirect($this->route('objetivos', $project))->with('status', $this->tracking->updateProduct($project, $id, $data['done']));
    }

    public function storeProduct(Request $request, Project $project)
    {
        $this->base($project, 'objetivos');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(ProjectTracking::PRODUCT_TYPES)],
            'target' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit' => ['required', 'string', 'max:20'],
            'due' => ['required', 'date', 'after_or_equal:' . $project->start_date->toDateString(), 'before_or_equal:' . $project->end_date->toDateString()],
        ], [
            'name.required' => 'Escribe el nombre del producto.',
            'due.after_or_equal' => 'La fecha debe estar dentro de la vigencia del proyecto.',
            'due.before_or_equal' => 'La fecha debe estar dentro de la vigencia del proyecto.',
        ]);

        return redirect($this->route('objetivos', $project))->with('status', $this->tracking->addProduct($project, $data));
    }

    // ---------- VIC 05 · Presupuesto por rubros ----------

    public function budget(Request $request, Project $project)
    {
        return view('vic.projects.budget', $this->base($project, 'presupuesto') + [
            't' => $this->finance->projectTotals($project),
            'movements' => $this->finance->movements($project),
            'transfers' => $this->finance->transfers($project),
            'modal' => $request->query('modal') === 'traslado' && $project->status === 'activo' ? 'traslado' : null,
        ]);
    }

    /** Traslado presupuestal: Vicerrectoría lo crea y aprueba en un paso; el investigador lo solicita. */
    public function transfer(Request $request, Project $project, ApprovalInbox $inbox)
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
            'amount.min' => 'El valor mínimo es $100.000.',
        ]);
        $this->base($project, 'presupuesto');
        $message = $this->finance->requestTransfer($project, $data);
        // Vicerrectoría lo aprueba de una vez; el investigador solo lo solicita
        if ($this->area() === 'vic') {
            $code = collect(DemoState::get('fin_transfers', []))->last()['code'];
            $message = $inbox->approve('traslado', (int) substr($code, 3), $data['reason'] ?? null);
        }

        return redirect($this->route('presupuesto', $project))->with('status', $message);
    }

    public function exportMovements(Project $project)
    {
        return csv_download('movimientos-' . Str::slug($project->code), ['Fecha', 'Rubro', 'Descripción', 'Origen', 'Valor'],
            $this->finance->movements($project)->map(fn ($m) => [$m['date']->toDateString(), $m['line'], $m['description'], $m['origin'], (int) $m['value']]));
    }

    // ---------- VIC 07 · Avances mensuales ----------

    public function progress(Request $request, Project $project)
    {
        $base = $this->base($project, 'avances');
        $review = $request->boolean('revisar') && $base['pendingProgress'] ? $base['pendingProgress'] : null;
        $products = $this->tracking->products($project);

        return view('vic.projects.progress', $base + [
            'calendar' => $this->tracking->reportCalendar($project),
            'reports' => $this->tracking->reports($project),
            'review' => $review,
            'deliveredInMonth' => $review ? $products->filter(fn ($p) => $p['due']->isSameMonth($review->period)) : collect(),
            'loading' => $base['nextReport'] && ($request->boolean('cargar') || old('_form') === 'avance'),
            'products' => $products,
        ]);
    }

    /** El investigador carga el avance mensual (modal «Cargar avance mensual»). */
    public function storeReport(Request $request, Project $project)
    {
        $base = $this->base($project, 'avances');
        abort_unless($base['nextReport'], 409, 'No hay avances pendientes por cargar.');
        $lines = $project->budgetLines->keys()->all();
        $data = $request->validate([
            'period' => ['required', 'date'],
            'products' => ['array'],
            'products.*' => ['nullable', 'integer', 'min:0'],
            'expenses' => ['array'],
            'expenses.*.line' => ['nullable', Rule::in($lines)],
            'expenses.*.concept' => ['nullable', 'string', 'max:120'],
            'expenses.*.value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['required', 'string', 'max:1000'],
            'files.*' => ['nullable', 'file', 'max:10240'],
        ], ['notes.required' => 'Describe las actividades realizadas en el periodo.']);
        $data['expenses'] = collect($data['expenses'] ?? [])->filter(fn ($e) => ! empty($e['value']))->values()->all();

        return redirect($this->route('avances', $project))
            ->with('status', $this->tracking->addReport($project, $data, count($request->file('files', []))));
    }

    public function decideProgress(Request $request, Project $project, int $id, ApprovalInbox $inbox)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['aprobar', 'devolver'])],
            'comment' => ['nullable', 'string', 'max:500', 'required_if:decision,devolver'],
        ], ['comment.required_if' => 'Escribe las observaciones para el coordinador.']);
        $message = $data['decision'] === 'aprobar'
            ? $inbox->approve('avance', $id, $data['comment'] ?? null)
            : $inbox->returnProgress($id, $data['comment']);

        return redirect($this->route('avances', $project))->with('status', $message);
    }

    // ---------- VIC 08 · Compras del proyecto ----------

    public function purchases(Request $request, Project $project)
    {
        $all = $this->finance->purchases()->where('project_id', $project->id)->values();
        $tab = array_key_exists($request->query('estado'), PurchaseController::TABS) ? $request->query('estado') : 'todas';
        $statuses = PurchaseController::TABS[$tab][1];

        return view('vic.projects.purchases', $this->base($project, 'compras') + [
            'list' => $statuses ? $all->whereIn('status', $statuses)->values() : $all,
            'tab' => $tab,
            'tabs' => collect(PurchaseController::TABS)->map(fn ($t) => ['label' => $t[0], 'count' => $t[1] ? $all->whereIn('status', $t[1])->count() : $all->count()]),
        ]);
    }

    // ---------- VIC 09 · Documentos y RUP ----------

    public function documents(Request $request, Project $project)
    {
        $all = $this->finance->documents()->where('project.id', $project->id)->values();
        $cat = array_key_exists($request->query('tipo'), FinanceStore::DOC_CATEGORIES) ? $request->query('tipo') : null;
        $checklist = collect(FinanceStore::RUP_DOCS)->map(fn ($type) => [
            'type' => $type, 'done' => $all->where('type', $type)->where('validated', true)->isNotEmpty(),
        ]);

        return view('vic.projects.documents', $this->base($project, 'documentos') + [
            'all' => $all,
            'list' => $cat ? $all->where('category', $cat)->values() : $all,
            'cat' => $cat,
            'checklist' => $checklist,
            'modal' => $request->query('modal') === 'subir' || old('_form') === 'subir',
        ]);
    }

    public function upload(Request $request, Project $project)
    {
        $this->base($project, 'documentos');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(FinanceStore::DOC_TYPES))],
            'file' => ['required', 'file', 'max:1536', 'mimes:pdf,docx,xlsx,zip,png,jpg,jpeg'],
        ], [
            'file.required' => 'Selecciona un archivo.',
            'file.max' => 'En esta demo el archivo puede pesar hasta 1,5 MB.',
            'file.mimes' => 'Formatos permitidos: PDF, DOCX, XLSX, ZIP o imágenes.',
        ]);

        return redirect($this->route('documentos', $project))->with('status', $this->finance->upload($project, $data['type'], $request->file('file')));
    }

    public function validateDocument(Project $project, string $id)
    {
        return back()->with('status', $this->finance->validateDocument($id));
    }

    /** Certificación de experiencia para la Cámara de Comercio (página imprimible). */
    public function certificate(Project $project)
    {
        $docs = $this->finance->documents()->where('project.id', $project->id);
        $complete = collect(FinanceStore::RUP_DOCS)->every(fn ($t) => $docs->where('type', $t)->where('validated', true)->isNotEmpty());
        abort_unless($complete, 409, 'Faltan documentos del checklist RUP para generar la certificación.');

        return view('vic.certificate', ['project' => $project, 'products' => $this->tracking->products($project)]);
    }

    // ---------- VIC 10 · Trazabilidad ----------

    public function timeline(Project $project)
    {
        return view('vic.projects.timeline', $this->base($project, 'trazabilidad') + [
            'events' => $this->tracking->timeline($project),
        ]);
    }
}
