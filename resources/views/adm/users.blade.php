@extends('layouts.app')
@section('title', 'Usuarios y roles')
@section('content')
    @php
        use App\Models\User;
        use App\Support\AdminStore;
        $roleIcons = ['investigador' => 'idcard', 'vicerrectoria' => 'bank', 'financiero' => 'money', 'admin' => 'shield'];
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Oficina TIC · Administración</div>
            <h1>Usuarios y roles</h1>
            <p class="sub">Administración de cuentas, roles y permisos del sistema</p>
        </div>
        <div class="actions">
            <form method="post" action="{{ route('adm.reset') }}" onsubmit="return confirm('¿Restablecer los datos demo? Se perderán los cambios de esta sesión.')">
                @csrf
                <button class="btn btn-danger" type="submit"><x-icon name="refresh" size="16" />Restablecer datos demo</button>
            </form>
            <a class="btn btn-primary" href="{{ route('adm.usuarios', ['modal' => 'nuevo']) }}"><x-icon name="userplus" size="16" />Nuevo usuario</a>
            <x-notifications />
        </div>
    </div>

    <div class="grid-4">
        @foreach (User::ROLES as $key => $label)
            <div class="card role-card">
                <span class="stat-label"><span class="stat-icon"><x-icon :name="$roleIcons[$key]" size="16" /></span>{{ $label }}</span>
                <span class="stat-value">{{ $roleCounts[$key] }} {{ $roleCounts[$key] === 1 ? 'usuario' : 'usuarios' }}</span>
                <p>{{ User::ROLE_DESCRIPTIONS[$key] }}</p>
            </div>
        @endforeach
    </div>

    <section class="card" style="margin-bottom:20px">
        <h2 style="margin-bottom:12px">Usuarios</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nombre</th><th>Correo</th><th>Dependencia</th><th>Rol</th><th>Proyectos</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr>
                        <td><span class="user-cell"><span class="avatar" aria-hidden="true">{{ $u['initials'] }}</span>{{ $u['name'] }}</span></td>
                        <td>{{ $u['email'] }}</td>
                        <td class="muted" style="font-size:13px">{{ $u['department'] ?: '—' }}</td>
                        <td><span class="badge gray">{{ $u['role_label'] }}</span></td>
                        <td>{{ $u['projects'] }}</td>
                        <td><span class="badge {{ $u['active'] ? 'green' : 'gray' }}">{{ $u['active'] ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="num"><a class="btn btn-sm" href="{{ route('adm.usuarios', ['editar' => $u['id']]) }}"><x-icon name="edit" size="14" />Editar<span class="sr-only"> {{ $u['name'] }}</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2 style="margin-bottom:12px">Matriz de permisos por rol</h2>
        <div class="table-wrap">
            <table class="matrix">
                <thead><tr><th>Permiso</th>@foreach (User::ROLES as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach (AdminStore::PERMISSIONS as $perm => $flags)
                    <tr>
                        <td>{{ $perm }}</td>
                        @foreach ($flags as $i => $ok)
                            <td>
                                @if ($ok)
                                    <x-icon name="checkc" size="16" class="yes" /><span class="sr-only">Sí</span>
                                @else
                                    <span class="no" aria-hidden="true">–</span><span class="sr-only">No</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($modal)
        @php
            $u = $editing ?? ['name' => '', 'email' => '', 'role' => 'investigador', 'department' => '', 'active' => true];
            $role = old('role', $u['role']);
        @endphp
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" method="post"
                  action="{{ $editing ? route('adm.usuarios.update', $editing['id']) : route('adm.usuarios.store') }}">
                @csrf
                @if ($editing) @method('PUT') @else <input type="hidden" name="_form" value="nuevo"> @endif
                <div class="modal-head">
                    <h2 id="modal-title">{{ $editing ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
                    <a class="btn btn-icon" href="{{ route('adm.usuarios') }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="field">
                        <label for="name">Nombre completo</label>
                        <input id="name" name="name" value="{{ old('name', $u['name']) }}" required autofocus>
                        @error('name')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="email">Correo institucional</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $u['email']) }}" placeholder="nombre@fet.edu.co" required>
                        @error('email')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="row">
                        <div class="field">
                            <label for="role">Rol</label>
                            <select id="role" name="role" onchange="document.getElementById('role-desc').textContent = this.selectedOptions[0].dataset.desc">
                                @foreach (User::ROLES as $key => $label)
                                    <option value="{{ $key }}" data-desc="{{ User::ROLE_DESCRIPTIONS[$key] }}" @selected($role === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="department">Dependencia</label>
                            <input id="department" name="department" value="{{ old('department', $u['department']) }}">
                        </div>
                    </div>
                    <label class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $u['active']))>Usuario activo</label>
                    <p class="modal-note" id="role-desc">{{ User::ROLE_DESCRIPTIONS[$role] }}</p>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ route('adm.usuarios') }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    @endif
@endsection
