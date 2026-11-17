<?php

namespace App\Http\Controllers;

use App\Support\DemoData;
use App\Support\EntityStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Catálogo de entidades contratantes y aliadas (Vicerrectoría y Área financiera). */
class EntityController extends Controller
{
    public const FILTERS = ['todas' => 'Todas', 'completas' => 'Documentación completa', 'pendientes' => 'Con documentos pendientes', 'inactivas' => 'Inactivas'];

    public function __construct(private EntityStore $store) {}

    public function index(Request $request)
    {
        $projects = DemoData::get()->projects->whereIn('status', ['activo', 'liquidado', 'propuesto']);
        $all = EntityStore::all()->map(function ($e) use ($projects) {
            $checklist = $this->store->checklist($e);

            return $e + [
                'projects' => $projects->where('entity_id', $e['id'])->values(),
                'checklist' => $checklist,
                'done' => $checklist->where('done', true)->count(),
                'complete' => $checklist->every('done'),
            ];
        });

        $filter = array_key_exists($request->query('filtro'), self::FILTERS) ? $request->query('filtro') : 'todas';
        $q = trim((string) $request->query('q'));
        $needle = Str::lower(Str::ascii($q));
        $list = $all
            ->filter(fn ($e) => match ($filter) {
                'completas' => $e['active'] && $e['complete'],
                'pendientes' => $e['active'] && ! $e['complete'],
                'inactivas' => ! $e['active'],
                default => true,
            })
            ->filter(fn ($e) => $needle === '' || Str::contains(Str::lower(Str::ascii("{$e['name']} {$e['nit']} {$e['city']}")), $needle))
            ->values();

        $viewing = $request->integer('ver') ? $all->firstWhere('id', $request->integer('ver')) : null;
        $editing = $request->integer('editar') ? (EntityStore::find($request->integer('editar')) ?? abort(404)) : null;

        return view('entities.index', [
            'all' => $all,
            'list' => $list,
            'filter' => $filter,
            'q' => $q,
            'viewing' => $viewing,
            'documents' => $viewing ? $this->store->documents($viewing) : collect(),
            'uploading' => $viewing && ($request->query('modal') === 'subir' || old('_form') === 'subir-documento'),
            'editing' => $editing,
            'creating' => ! $editing && ($request->query('modal') === 'nueva' || old('_form') === 'nueva-entidad'),
        ]);
    }

    public function store(Request $request)
    {
        $entity = $this->store->save($this->validated($request));

        return redirect()->route('entidades.index', ['ver' => $entity['id']])
            ->with('status', "Entidad «{$entity['name']}» creada. Carga ahora sus documentos (RUT, representación legal…).");
    }

    public function update(Request $request, int $id)
    {
        abort_unless(EntityStore::find($id), 404);
        $entity = $this->store->save($this->validated($request, $id), $id);

        return redirect()->route('entidades.index', ['ver' => $id])->with('status', "Cambios de «{$entity['name']}» guardados.");
    }

    public function upload(Request $request, int $id)
    {
        $entity = EntityStore::find($id) ?? abort(404);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(EntityStore::DOC_TYPES))],
            'file' => ['required', 'file', 'max:1536', 'mimes:pdf,png,jpg,jpeg'],
        ], [
            'file.required' => 'Selecciona un archivo.',
            'file.max' => 'En esta demo el archivo puede pesar hasta 1,5 MB.',
            'file.mimes' => 'Formatos permitidos: PDF o imagen (PNG, JPG).',
        ]);

        return redirect()->route('entidades.index', ['ver' => $id])->with('status', $this->store->upload($entity, $data['type'], $request->file('file')));
    }

    /** Descarga (o muestra en línea con ?ver=1) un documento de la entidad. */
    public function download(Request $request, string $doc)
    {
        $d = $this->store->document($doc);
        abort_unless($d['path'] && Storage::exists($d['path']), 404, 'Este documento es de ejemplo y no tiene archivo.');

        return $request->boolean('ver')
            ? Storage::response($d['path'], $d['name'], ['Content-Disposition' => 'inline'])
            : Storage::download($d['path'], $d['name']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $others = EntityStore::all()->reject(fn ($e) => $e['id'] === $id);

        return $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::notIn($others->pluck('name')->all())],
            'nit' => ['required', 'string', 'max:20', 'regex:/^[0-9.]{6,15}(-[0-9])?$/', Rule::notIn($others->pluck('nit')->all())],
            'type' => ['required', Rule::in(array_keys(EntityStore::TYPES))],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'legal_rep' => ['nullable', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Escribe la razón social o nombre de la entidad.',
            'name.not_in' => 'Ya existe una entidad con ese nombre.',
            'nit.required' => 'Escribe el NIT.',
            'nit.regex' => 'Escribe el NIT con números y el dígito de verificación, por ejemplo 891.180.009-1.',
            'nit.not_in' => 'Ya existe una entidad con ese NIT.',
            'city.required' => 'Escribe el municipio.',
        ]) + ['address' => '', 'phone' => '', 'email' => '', 'legal_rep' => '', 'contact_name' => '', 'contact_email' => '', 'contact_phone' => ''];
    }
}
