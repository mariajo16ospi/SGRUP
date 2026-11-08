@extends('layouts.app')
@section('title', 'Parámetros del sistema')
@section('content')
    @php use App\Support\AdminStore; @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Oficina TIC · Administración</div>
            <h1>Parámetros del sistema</h1>
            <p class="sub">Catálogos y reglas que usan todos los proyectos</p>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('adm.parametros', ['modal' => 'nuevo']) }}"><x-icon name="plus" size="16" />Nuevo rubro</a>
            <a class="btn btn-icon" href="{{ route('adm.auditoria') }}" aria-label="Actividad reciente"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px">
        <h2>Rubros presupuestales</h2>
        <p class="hint" style="margin-bottom:12px">{{ $activeItems }} {{ $activeItems === 1 ? 'rubro activo' : 'rubros activos' }}</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Código</th><th>Rubro</th><th>Requiere CDP</th><th>Proyectos que lo usan</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                <tbody>
                @foreach ($items as $r)
                    <tr>
                        <td class="strong">{{ $r['code'] }}</td>
                        <td><div style="font-weight:500">{{ $r['name'] }}</div>@if ($r['description'])<div class="meta">{{ $r['description'] }}</div>@endif</td>
                        <td>{{ $r['cdp'] ? 'Sí' : 'No' }}</td>
                        <td>{{ $r['projects'] }}</td>
                        <td><span class="badge {{ $r['active'] ? 'green' : 'gray' }}">{{ $r['active'] ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="num"><a class="btn btn-sm" href="{{ route('adm.parametros', ['editar' => $r['code']]) }}">Editar<span class="sr-only"> {{ $r['name'] }}</span></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid-3">
        <section class="card">
            <h2>Fases del proyecto</h2>
            <p class="hint" style="margin-bottom:14px">Ruta de ejecución tipo BPIN</p>
            <div class="list-rows">
                @foreach (AdminStore::PHASES as $i => [$name, $desc])
                    <div style="justify-content:flex-start"><span class="step">{{ $i + 1 }}</span><span><b>{{ $name }}</b><span class="meta">{{ $desc }}</span></span></div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <h2>Documentos obligatorios</h2>
            <p class="hint" style="margin-bottom:14px">Se exigen antes de cambiar de fase</p>
            <div class="list-rows">
                @foreach (AdminStore::REQUIRED_DOCUMENTS as [$doc, $phase])
                    <div><b style="font-weight:500">{{ $doc }}</b><span class="badge gray">{{ $phase }}</span></div>
                @endforeach
            </div>
        </section>

        <section class="card">
            <h2>Reglas de alerta</h2>
            <p class="hint" style="margin-bottom:14px">Se aplican automáticamente</p>
            <div class="list-rows">
                @foreach ($rules as $rule)
                    <div>
                        <span><b>{{ $rule['title'] }}</b><span class="meta">{{ $rule['detail'] }}</span></span>
                        <form method="post" action="{{ route('adm.reglas.toggle', $rule['key']) }}">
                            @csrf
                            <button class="switch" type="submit" role="switch" aria-checked="{{ $rule['on'] ? 'true' : 'false' }}" aria-label="{{ $rule['title'] }}"></button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    @if ($modal)
        @php $r = $editing ?? ['code' => $nextCode, 'name' => '', 'description' => '', 'cdp' => true, 'active' => true]; @endphp
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" method="post"
                  action="{{ $editing ? route('adm.rubros.update', $editing['code']) : route('adm.rubros.store') }}">
                @csrf
                @if ($editing) @method('PUT') @else <input type="hidden" name="_form" value="nuevo"> @endif
                <div class="modal-head">
                    <div>
                        <h2 id="modal-title">{{ $editing ? 'Editar rubro presupuestal' : 'Nuevo rubro presupuestal' }}</h2>
                        <p>Parámetro disponible para todos los proyectos</p>
                    </div>
                    <a class="btn btn-icon" href="{{ route('adm.parametros') }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="row code">
                        <div class="field">
                            <label for="code">Código *</label>
                            <input id="code" name="code" value="{{ old('code', $r['code']) }}" required>
                            @error('code')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="name">Nombre del rubro *</label>
                            <input id="name" name="name" value="{{ old('name', $r['name']) }}" required autofocus>
                            @error('name')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="field">
                        <label for="description">Descripción</label>
                        <textarea id="description" name="description">{{ old('description', $r['description']) }}</textarea>
                    </div>
                    <div class="field">
                        <label for="cdp">Requiere CDP previo</label>
                        <select id="cdp" name="cdp">
                            <option value="1" @selected(old('cdp', $r['cdp'] ? '1' : '0') === '1')>Sí</option>
                            <option value="0" @selected(old('cdp', $r['cdp'] ? '1' : '0') === '0')>No</option>
                        </select>
                    </div>
                    <label class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $r['active']))>Rubro activo</label>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ route('adm.parametros') }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar rubro</button>
                </div>
            </form>
        </div>
    @endif
@endsection
