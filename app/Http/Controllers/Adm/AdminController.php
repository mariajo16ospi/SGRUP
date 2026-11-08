<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminStore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Vista del Administrador del sistema (mockups ADM 01–03). */
class AdminController extends Controller
{
    public function __construct(private AdminStore $store) {}

    // ---------- ADM 01 · Usuarios y roles ----------

    public function users(Request $request)
    {
        $users = $this->store->users();
        $editing = $request->integer('editar') ? $this->store->findUser($request->integer('editar')) : null;

        return view('adm.users', [
            'users' => $users,
            'roleCounts' => collect(User::ROLES)->map(fn ($label, $key) => $users->where('role', $key)->count()),
            'editing' => $editing,
            'modal' => $editing ? 'editar' : ($request->query('modal') === 'nuevo' || old('_form') === 'nuevo' ? 'nuevo' : null),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data = $this->validateUser($request);
        $user = $this->store->saveUser($data);
        $this->store->log('Creó usuario', 'gray', 'Usuarios', "{$user['email']} · {$user['role_label']}");

        return redirect()->route('adm.usuarios')->with('status', "Usuario {$user['name']} creado.");
    }

    public function updateUser(Request $request, int $id)
    {
        abort_unless($this->store->findUser($id), 404);
        $data = $this->validateUser($request, $id);
        $user = $this->store->saveUser($data, $id);
        $this->store->log('Editó usuario', 'gray', 'Usuarios', "{$user['email']} · {$user['role_label']}" . ($user['active'] ? '' : ' · inactivo'));

        return redirect()->route('adm.usuarios')->with('status', "Cambios de {$user['name']} guardados.");
    }

    private function validateUser(Request $request, ?int $id = null): array
    {
        $taken = $this->store->users()->reject(fn ($u) => $u['id'] === $id)->pluck('email')->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'ends_with:@fet.edu.co', Rule::notIn($taken)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'department' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Escribe el nombre completo.',
            'email.required' => 'Escribe el correo institucional.',
            'email.email' => 'El correo no es válido.',
            'email.ends_with' => 'El correo debe ser institucional (@fet.edu.co).',
            'email.not_in' => 'Ya existe un usuario con ese correo.',
        ]);
    }

    public function reset()
    {
        $this->store->reset();

        return redirect()->route('adm.usuarios')->with('status', 'Datos demo restablecidos.');
    }

    // ---------- ADM 02 · Parámetros del sistema ----------

    public function parameters(Request $request)
    {
        $editing = $request->query('editar') ? $this->store->findItem($request->query('editar')) : null;
        $items = $this->store->budgetItems();

        return view('adm.parameters', [
            'items' => $items,
            'activeItems' => $items->where('active', true)->count(),
            'rules' => $this->store->rules(),
            'editing' => $editing,
            'nextCode' => $this->store->nextItemCode(),
            'modal' => $editing ? 'editar' : ($request->query('modal') === 'nuevo' || old('_form') === 'nuevo' ? 'nuevo' : null),
        ]);
    }

    public function storeItem(Request $request)
    {
        $data = $this->validateItem($request);
        $this->store->saveItem($data);
        $this->store->log('Creó rubro', 'gray', 'Parámetros', "{$data['code']} · {$data['name']}");

        return redirect()->route('adm.parametros')->with('status', "Rubro {$data['code']} · {$data['name']} creado.");
    }

    public function updateItem(Request $request, string $code)
    {
        abort_unless($this->store->findItem($code), 404);
        $data = $this->validateItem($request, $code);
        $this->store->saveItem($data, $code);
        $this->store->log('Cambió parámetro', 'gray', 'Parámetros', "Rubro {$data['code']} · {$data['name']}");

        return redirect()->route('adm.parametros')->with('status', "Rubro {$data['code']} actualizado.");
    }

    private function validateItem(Request $request, ?string $code = null): array
    {
        $taken = $this->store->budgetItems()->pluck('code')->reject(fn ($c) => $c === $code)->all();

        return $request->validate([
            'code' => ['required', 'regex:/^R-\d{2}$/', Rule::notIn($taken)],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'cdp' => ['required', Rule::in(['0', '1'])],
            'active' => ['nullable', 'boolean'],
        ], [
            'code.required' => 'Escribe el código del rubro.',
            'code.regex' => 'El código debe tener el formato R-00.',
            'code.not_in' => 'Ya existe un rubro con ese código.',
            'name.required' => 'Escribe el nombre del rubro.',
        ]);
    }

    public function toggleRule(string $key)
    {
        abort_unless(array_key_exists($key, AdminStore::DEFAULT_RULES), 404);
        $rule = $this->store->toggleRule($key);
        $state = $rule['on'] ? 'activada' : 'desactivada';
        $this->store->log('Cambió parámetro', 'gray', 'Parámetros', "Regla «{$rule['title']}» {$state}");

        return redirect()->route('adm.parametros')->with('status', "Regla «{$rule['title']}» {$state}.");
    }

    // ---------- ADM 03 · Auditoría ----------

    public function audit(Request $request)
    {
        [$rows, $filters] = $this->filteredAudit($request);

        return view('adm.audit', [
            'rows' => $rows->take(50),
            'total' => $rows->count(),
            'filters' => $filters,
            'userOptions' => $this->store->audit()->pluck('user')->unique()->sort()->values(),
        ]);
    }

    public function exportAudit(Request $request)
    {
        [$rows] = $this->filteredAudit($request);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha y hora', 'Usuario', 'Rol', 'Módulo', 'Acción', 'Detalle'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [$r['at']->format('Y-m-d H:i'), $r['user'], $r['role'], $r['module'], $r['action'], $r['detail']], ';');
            }
            fclose($out);
        }, 'auditoria-sgrup-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredAudit(Request $request): array
    {
        $filters = [
            'usuario' => (string) $request->query('usuario', ''),
            'modulo' => in_array($request->query('modulo'), AdminStore::MODULES, true) ? $request->query('modulo') : '',
            'desde' => $this->date($request->query('desde')) ?? Carbon::parse('2026-10-01'),
            'hasta' => $this->date($request->query('hasta')) ?? today(),
        ];

        $rows = $this->store->audit()
            ->filter(fn ($r) => $filters['usuario'] === '' || $r['user'] === $filters['usuario'])
            ->filter(fn ($r) => $filters['modulo'] === '' || $r['module'] === $filters['modulo'])
            ->filter(fn ($r) => $r['at']->betweenIncluded($filters['desde']->copy()->startOfDay(), $filters['hasta']->copy()->endOfDay()))
            ->values();

        return [$rows, $filters];
    }

    private function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::createFromFormat('Y-m-d', $value)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
