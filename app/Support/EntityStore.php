<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Catálogo de entidades contratantes y aliadas, con sus datos y documentos
 * legales (RUT, existencia y representación legal, etc.).
 *
 * Versión de diseño: las entidades de ejemplo salen de los proyectos de los
 * mockups; las creadas, las ediciones y los documentos se guardan en DemoState.
 * Los proyectos se enlazan por `entity_id` y toman el nombre de aquí.
 */
class EntityStore
{
    public const TYPES = [
        'publica' => 'Entidad pública',
        'privada' => 'Empresa privada',
        'mixta' => 'Economía mixta',
        'sin_lucro' => 'Sin ánimo de lucro',
        'educativa' => 'Institución educativa',
    ];

    /** Documentos que se pueden cargar de una entidad, con su ayuda. */
    public const DOC_TYPES = [
        'RUT' => 'Registro Único Tributario expedido por la DIAN',
        'Existencia y representación legal' => 'Certificado de la Cámara de Comercio (no mayor a 30 días)',
        'Acto de creación' => 'Ley, ordenanza, acuerdo o decreto que crea la entidad',
        'Acto de nombramiento y posesión' => 'Del representante legal (alcalde, gobernador, secretario…)',
        'Cédula del representante legal' => 'Copia legible por ambas caras',
        'Certificación bancaria' => 'Cuenta a nombre de la entidad (no mayor a 30 días)',
        'Otro' => 'Cualquier otro soporte de la entidad',
    ];

    /** Checklist de documentos obligatorios según el tipo de entidad. */
    public static function required(string $type): array
    {
        return $type === 'publica'
            ? ['RUT', 'Acto de creación', 'Acto de nombramiento y posesión', 'Cédula del representante legal', 'Certificación bancaria']
            : ['RUT', 'Existencia y representación legal', 'Cédula del representante legal', 'Certificación bancaria'];
    }

    /** Entidades de ejemplo: las de los proyectos de los mockups. Los NIT son ficticios. */
    private const SEED = [
        [1, 'Alcaldía de Neiva', '800.100.001-1', 'publica', 'Neiva', ['RUT', 'Acto de creación', 'Acto de nombramiento y posesión', 'Cédula del representante legal', 'Certificación bancaria']],
        [2, 'Gobernación del Huila', '800.100.002-2', 'publica', 'Neiva', ['RUT', 'Acto de nombramiento y posesión', 'Certificación bancaria']],
        [3, 'FET – Recursos propios', '800.100.003-3', 'educativa', 'Neiva', ['RUT', 'Existencia y representación legal', 'Cédula del representante legal', 'Certificación bancaria']],
        [4, 'Cámara de Comercio del Huila', '800.100.004-4', 'privada', 'Neiva', ['RUT', 'Existencia y representación legal']],
        [5, 'Alcaldía de Pitalito', '800.100.005-5', 'publica', 'Pitalito', ['RUT', 'Acto de creación', 'Acto de nombramiento y posesión', 'Cédula del representante legal', 'Certificación bancaria']],
        [6, 'Asociación Hotelera del Huila', '800.100.006-6', 'sin_lucro', 'Villavieja', ['RUT']],
        [7, 'Secretaría de Educación del Huila', '800.100.007-7', 'publica', 'Neiva', ['RUT', 'Acto de creación', 'Acto de nombramiento y posesión', 'Cédula del representante legal', 'Certificación bancaria']],
        [8, 'Minciencias', '800.100.008-8', 'publica', 'Bogotá', ['RUT', 'Acto de creación', 'Acto de nombramiento y posesión', 'Cédula del representante legal', 'Certificación bancaria']],
        [9, 'Alcaldía de Garzón', '800.100.009-9', 'publica', 'Garzón', ['RUT']],
    ];

    /** @return Collection<int, array> todas las entidades, ordenadas por nombre */
    public static function all(): Collection
    {
        $changes = DemoState::get('entities', []);
        $base = collect(self::SEED)->map(fn ($r) => [
            'id' => $r[0], 'name' => $r[1], 'nit' => $r[2], 'type' => $r[3], 'city' => $r[4], 'address' => '', 'phone' => '',
            'email' => '', 'legal_rep' => '', 'contact_name' => '', 'contact_email' => '', 'contact_phone' => '', 'active' => true,
            'seed_docs' => $r[5],
        ]);
        $base = $base->map(fn ($e) => array_merge($e, $changes[$e['id']] ?? []));
        $created = collect($changes)->filter(fn ($e) => ! empty($e['new']))->map(fn ($e) => $e + ['seed_docs' => []]);

        return $base->concat($created)
            ->map(fn ($e) => $e + ['type_label' => self::TYPES[$e['type']] ?? $e['type']])
            ->sortBy(fn ($e) => Str::lower(Str::ascii($e['name'])))->values();
    }

    public static function find(int $id): ?array
    {
        return self::all()->firstWhere('id', $id);
    }

    /** Nombre original => id de las entidades de ejemplo (para enlazar los proyectos de ejemplo aunque se renombren). */
    public static function seedIds(): array
    {
        return array_column(self::SEED, 0, 1);
    }

    public function save(array $data, ?int $id = null): array
    {
        $changes = DemoState::get('entities', []);
        $data['active'] = (bool) ($data['active'] ?? false);
        if ($id === null) {
            $id = max(100, ...array_keys($changes ?: [0 => 0])) + 1;
            $data += ['id' => $id, 'new' => true];
        }
        $changes[$id] = array_merge($changes[$id] ?? [], $data);
        DemoState::put('entities', $changes);

        return self::find($id);
    }

    // ---------- Documentos ----------

    /** Documentos de la entidad: los de ejemplo (sin archivo) y los subidos en la demo. */
    public function documents(array $entity): Collection
    {
        $seed = collect($entity['seed_docs'] ?? [])->map(fn ($type, $i) => [
            'id' => "e{$entity['id']}-{$i}", 'entity_id' => $entity['id'], 'type' => $type, 'name' => Str::slug($type) . '.pdf',
            'ext' => 'PDF', 'by' => 'Dra. Marcela Trujillo', 'date' => Carbon::parse('2026-01-10')->addDays($entity['id'] * 3), 'size' => 0.4, 'path' => null,
        ]);
        $uploaded = collect(DemoState::get('entity_docs', []))->where('entity_id', $entity['id'])
            ->map(fn ($d) => ['date' => Carbon::parse($d['date'])] + $d);

        return $seed->concat($uploaded)->sortByDesc('date')->values();
    }

    public function document(string $id): array
    {
        $doc = collect(DemoState::get('entity_docs', []))->firstWhere('id', $id);
        if (! $doc) {
            [$entityId, $i] = array_map('intval', explode('-', substr($id, 1)) + [1 => -1]);
            $entity = self::find($entityId) ?? abort(404);
            $doc = $this->documents($entity)->firstWhere('id', $id) ?? abort(404);
        }

        return $doc;
    }

    /** Estado del checklist: cuántos obligatorios tiene cargados. */
    public function checklist(array $entity): Collection
    {
        $types = $this->documents($entity)->pluck('type')->all();

        return collect(self::required($entity['type']))->map(fn ($t) => ['type' => $t, 'done' => in_array($t, $types, true)]);
    }

    public function upload(array $entity, string $type, UploadedFile $file): string
    {
        $path = $file->store('demo-uploads/entidades');
        DemoState::push('entity_docs', [
            'id' => 'x' . Str::random(8), 'entity_id' => $entity['id'], 'type' => $type, 'name' => $file->getClientOriginalName(),
            'ext' => Str::upper($file->getClientOriginalExtension() ?: 'FILE'), 'by' => auth()->user()->name,
            'date' => now()->toDateTimeString(), 'size' => round($file->getSize() / 1_048_576, 1), 'path' => $path,
        ]);

        return "«{$file->getClientOriginalName()}» cargado como {$type} de {$entity['name']}.";
    }
}
