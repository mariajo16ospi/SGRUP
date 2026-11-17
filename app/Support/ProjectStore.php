<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Registro y edición de proyectos por Vicerrectoría (modal «Nuevo proyecto / contrato»).
 *
 * Versión de diseño: los proyectos y los cambios se guardan en DemoState y
 * DemoData los aplica al construir los datos.
 */
class ProjectStore
{
    /** Distribución sugerida por rubro (%), la del mockup. */
    public const DEFAULT_SPLIT = [
        'Talento humano' => 42, 'Equipos' => 18, 'Materiales e insumos' => 12, 'Viajes y transporte' => 8,
        'Capacitaciones y eventos' => 10, 'Servicios técnicos' => 6, 'Gastos administrativos' => 4,
    ];

    /** Nombres de los campos editables, para la trazabilidad. */
    public const FIELD_LABELS = [
        'name' => 'nombre', 'code' => 'código', 'type' => 'tipo', 'modality' => 'modalidad',
        'entity_id' => 'entidad', 'coordinator_id' => 'coordinador', 'general_objective' => 'objetivo general',
    ];

    public function __construct(private ProjectTracking $tracking) {}

    /** Usuarios que pueden coordinar un proyecto. */
    public function coordinators(): Collection
    {
        return DemoData::get()->users->where('role', 'investigador')->values();
    }

    /** Entidades que se pueden elegir (activas, y la actual del proyecto aunque esté inactiva). */
    public function entities(?Project $project = null): Collection
    {
        return EntityStore::all()->filter(fn ($e) => $e['active'] || ($project && $e['id'] === $project->entity_id))->values();
    }

    /** Códigos en uso, para no repetirlos. */
    public function codes(?Project $except = null): array
    {
        return DemoData::get()->projects->reject(fn ($p) => $except && $p->id === $except->id)->pluck('code')->all();
    }

    public static function unitFor(string $type): string
    {
        return match ($type) {
            'Capacitación' => 'personas',
            'Visita técnica' => 'visitas',
            default => 'und.',
        };
    }

    /** Registra un proyecto nuevo. Inicia activo, en fase de Preinversión. */
    public function create(array $data): Project
    {
        $id = DemoData::FIRST_NEW_ID + count(DemoState::get('vic_new_projects', []));
        $products = collect($data['products'] ?? [])->filter(fn ($p) => filled($p['name'] ?? null))
            ->map(fn ($p) => ['name' => $p['name'], 'type' => $p['type'], 'target' => (int) $p['target'], 'unit' => self::unitFor($p['type']), 'due' => $p['due']])
            ->values()->all();

        DemoState::push('vic_new_projects', [
            'id' => $id, 'code' => $data['code'], 'name' => $data['name'], 'type' => $data['type'], 'modality' => $data['modality'],
            'entity_id' => (int) $data['entity_id'], 'coordinator_id' => (int) $data['coordinator_id'], 'start' => $data['start'], 'end' => $data['end'],
            'value' => (float) $data['value'], 'objective' => $data['general_objective'],
            'split' => array_map('floatval', $data['split']), 'products' => $products, 'created_at' => now()->toDateTimeString(),
        ]);

        $project = DemoData::project($id);
        $this->tracking->log($project, "Registró el {$project->contractLabel()} {$project->code}",
            money($project->value) . ' · ' . date_es(Carbon::parse($data['start'])) . ' – ' . date_es(Carbon::parse($data['end'])));

        return $project;
    }

    /** Guarda los datos editados. Devuelve el mensaje de confirmación. */
    public function update(Project $project, array $data): string
    {
        $changed = collect(DemoData::EDITABLE)
            ->filter(fn ($f) => array_key_exists($f, $data) && (string) $project->{$f} !== (string) $data[$f])
            ->values();
        if ($changed->isEmpty()) {
            return 'No hubo cambios en los datos del proyecto.';
        }

        $edits = DemoState::get("vic_project_edits.{$project->id}", []);
        foreach ($changed as $f) {
            $edits[$f] = in_array($f, ['coordinator_id', 'entity_id'], true) ? (int) $data[$f] : $data[$f];
        }
        DemoState::put("vic_project_edits.{$project->id}", $edits);
        $this->tracking->log($project, 'Editó los datos del proyecto', 'Cambió: ' . $changed->map(fn ($f) => self::FIELD_LABELS[$f])->join(', ', ' y '));

        return "Datos de «{$data['name']}» actualizados.";
    }
}
