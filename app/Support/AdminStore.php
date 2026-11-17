<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Datos de la vista de Administración (mockups ADM 01–03).
 *
 * Versión de diseño: parte de los datos de ejemplo y guarda en la sesión los
 * usuarios, rubros y reglas que se crean o editan. "Restablecer datos demo"
 * borra esos cambios.
 */
class AdminStore
{
    public const SESSION_KEYS = ['adm_users', 'adm_items', 'adm_rules', 'adm_audit', 'approved'];

    public const MODULES = ['Compras', 'Pagos', 'Documentos', 'Avances', 'Usuarios', 'Parámetros', 'Sesión'];

    public const PERMISSIONS = [
        // permiso => [investigador, vicerrectoria, financiero, admin]
        'Ver proyectos propios' => [1, 1, 1, 1],
        'Ver todos los proyectos' => [0, 1, 1, 1],
        'Crear proyectos' => [0, 1, 0, 1],
        'Cargar avance mensual' => [1, 0, 0, 1],
        'Crear solicitudes de compra' => [1, 0, 0, 1],
        'Aprobar compras (revisión técnica)' => [0, 1, 0, 1],
        'Validar presupuesto y CDP' => [0, 0, 1, 1],
        'Registrar pagos' => [0, 0, 1, 1],
        'Traslados presupuestales' => [0, 1, 0, 1],
        'Subir documentos' => [1, 1, 1, 1],
        'Validar documentos' => [0, 1, 1, 1],
        'Cambiar fase / liquidar proyecto' => [0, 1, 0, 1],
        'Gestionar usuarios y roles' => [0, 0, 0, 1],
    ];

    public const PHASES = [
        ['Preinversión', 'Formulación, estudios y aprobación'],
        ['Inversión', 'Actividades, compras y productos'],
        ['Operación', 'Entrega y puesta en marcha'],
        ['Liquidación', 'Acta de liquidación y experiencia'],
    ];

    public const REQUIRED_DOCUMENTS = [
        ['Contrato o convenio', 'Preinversión'],
        ['Acta de inicio', 'Inversión'],
        ['Pólizas de cumplimiento', 'Inversión'],
        ['Informe mensual de avance', 'Inversión'],
        ['Acta de liquidación', 'Liquidación'],
        ['Certificado de experiencia (RUP)', 'Liquidación'],
    ];

    public const DEFAULT_RULES = [
        'sobrecosto' => ['Sobrecosto', 'Compra supera en 10 % lo cotizado', true],
        'recordatorio' => ['Recordatorio de avance', '5 días antes del corte mensual', true],
        'vencida' => ['Entrega vencida', 'Al día siguiente de la fecha', true],
        'plazo' => ['Plazo del proyecto', 'Cuando quede el 15 % del tiempo', true],
    ];

    private const BASE_ITEMS = [
        ['R-01', 'Talento humano', '', false, 6],
        ['R-02', 'Equipos', '', true, 4],
        ['R-03', 'Materiales e insumos', '', true, 5],
        ['R-04', 'Viáticos y transporte', '', false, 6],
        ['R-05', 'Capacitaciones y eventos', '', true, 3],
        ['R-06', 'Servicios técnicos', '', true, 4],
        ['R-07', 'Publicaciones y divulgación', '', false, 2],
        ['R-08', 'Imprevistos', '', true, 6],
    ];

    // ---------- Usuarios ----------

    /** Todos los usuarios: los de ejemplo con sus ediciones y los creados en la sesión. */
    public function users(): Collection
    {
        $changes = DemoState::get('adm_users', []);
        $d = DemoData::get();

        $base = $d->users->values()->map(function (User $u) use ($changes, $d) {
            $row = [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role,
                'department' => $u->department, 'active' => true, 'demo' => true,
                'projects' => $d->projects->where('coordinator_id', $u->id)->whereIn('status', ['activo', 'liquidado'])->count(),
            ];

            return array_merge($row, $changes[$u->id] ?? []);
        });

        $created = collect($changes)->filter(fn ($r) => ! empty($r['new']))->values()
            ->map(fn ($r) => $r + ['projects' => 0, 'demo' => false]);

        return $base->concat($created)->map(function ($r) {
            $r['role_label'] = User::ROLES[$r['role']];
            $r['initials'] = (new User(['name' => $r['name']]))->initials();

            return $r;
        });
    }

    public function findUser(int $id): ?array
    {
        return $this->users()->firstWhere('id', $id);
    }

    public function saveUser(array $data, ?int $id = null): array
    {
        $changes = DemoState::get('adm_users', []);
        $data['active'] = (bool) ($data['active'] ?? false);
        $data['department'] ??= '';
        if ($id === null) {
            $id = max(100, ...array_keys($changes ?: [0 => 0])) + 1;
            $data['new'] = true;
            $data['id'] = $id;
        }
        $changes[$id] = array_merge($changes[$id] ?? [], $data);
        DemoState::put('adm_users', $changes);

        return $this->findUser($id);
    }

    // ---------- Rubros ----------

    public function budgetItems(): Collection
    {
        $changes = DemoState::get('adm_items', []);
        $base = collect(self::BASE_ITEMS)->map(fn ($r) => array_merge(
            ['code' => $r[0], 'name' => $r[1], 'description' => $r[2], 'cdp' => $r[3], 'projects' => $r[4], 'active' => true],
            $changes[$r[0]] ?? []
        ));
        $created = collect($changes)->except($base->pluck('code'))->values()->map(fn ($r) => $r + ['projects' => 0]);

        return $base->concat($created)->values();
    }

    public function findItem(string $code): ?array
    {
        return $this->budgetItems()->firstWhere('code', $code);
    }

    public function nextItemCode(): string
    {
        $n = $this->budgetItems()->map(fn ($r) => (int) substr($r['code'], 2))->max() + 1;

        return sprintf('R-%02d', $n);
    }

    public function saveItem(array $data, ?string $originalCode = null): void
    {
        $changes = DemoState::get('adm_items', []);
        $data['cdp'] = $data['cdp'] === '1';
        $data['description'] ??= '';
        $data['active'] = (bool) ($data['active'] ?? false);
        if ($originalCode && $originalCode !== $data['code']) {
            unset($changes[$originalCode]);
        }
        $changes[$data['code']] = array_merge($changes[$originalCode ?? $data['code']] ?? [], $data);
        DemoState::put('adm_items', $changes);
    }

    // ---------- Reglas de alerta ----------

    public function rules(): array
    {
        $state = DemoState::get('adm_rules', []);

        return collect(self::DEFAULT_RULES)->map(fn ($r, $key) => [
            'key' => $key, 'title' => $r[0], 'detail' => $r[1], 'on' => $state[$key] ?? $r[2],
        ])->all();
    }

    public function toggleRule(string $key): array
    {
        $rule = $this->rules()[$key];
        DemoState::put("adm_rules.{$key}", ! $rule['on']);

        return $this->rules()[$key];
    }

    // ---------- Auditoría ----------

    /** Registro de auditoría: acciones de la sesión primero, luego las de ejemplo. */
    public function audit(): Collection
    {
        $seed = collect([
            ['2026-11-02 08:14', 'Dra. Marcela Trujillo', 'Vicerrectoría', 'Devolvió solicitud', 'amber', 'Compras', 'SC-0231 · pidió cotizaciones adicionales'],
            ['2026-11-01 17:40', 'Carlos Rojas', 'Área financiera', 'Registró pago', 'green', 'Pagos', 'SC-0226 · CE-2026-1180 · $2.900.000'],
            ['2026-11-01 11:02', 'Laura Méndez', 'Investigadora', 'Subió documento', 'blue', 'Documentos', 'Informe mensual septiembre.pdf'],
            ['2026-10-31 16:25', 'Dra. Marcela Trujillo', 'Vicerrectoría', 'Aprobó avance', 'green', 'Avances', 'Fortalecimiento TIC · agosto 2026'],
            ['2026-10-31 09:10', 'Daniel Rojas', 'Administrador', 'Creó usuario', 'gray', 'Usuarios', 'paola.cuellar@fet.edu.co · Investigador'],
            ['2026-10-30 15:47', 'Carlos Rojas', 'Área financiera', 'Expidió CDP', 'green', 'Pagos', 'CDP-2026-0402 · SC-0225'],
            ['2026-10-30 10:30', 'Andrés Perdomo', 'Investigador', 'Creó solicitud', 'blue', 'Compras', 'SC-0236 · Licencia ArcGIS Pro'],
            ['2026-10-29 14:05', 'Dra. Marcela Trujillo', 'Vicerrectoría', 'Rechazó solicitud', 'red', 'Compras', 'SC-0219 · fuera del alcance del convenio'],
            ['2026-10-28 08:55', 'Daniel Rojas', 'Administrador', 'Cambió parámetro', 'gray', 'Parámetros', 'Regla de sobrecosto: 10 %'],
            ['2026-10-27 19:12', 'Laura Méndez', 'Investigadora', 'Inició sesión', 'gray', 'Sesión', 'IP 190.85.12.44 · Chrome'],
        ])->map(fn ($r) => array_combine(['at', 'user', 'role', 'action', 'tone', 'module', 'detail'], $r));

        return collect(DemoState::get('adm_audit', []))->concat($seed)
            ->map(fn ($r) => ['at' => Carbon::parse($r['at'])] + $r)
            ->sortByDesc('at')->values();
    }

    public function log(string $action, string $tone, string $module, string $detail): void
    {
        DemoState::push('adm_audit', [
            'at' => now()->format('Y-m-d H:i'), 'user' => auth()->user()->name, 'role' => 'Administrador',
            'action' => $action, 'tone' => $tone, 'module' => $module, 'detail' => $detail,
        ]);
    }

    public function reset(): void
    {
        DemoState::reset();
    }
}
