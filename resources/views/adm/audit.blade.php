@extends('layouts.app')
@section('title', 'Auditoría')
@section('content')
    @php
        use App\Support\AdminStore;
        $query = array_filter([
            'usuario' => $filters['usuario'], 'modulo' => $filters['modulo'],
            'desde' => $filters['desde']->toDateString(), 'hasta' => $filters['hasta']->toDateString(),
        ]);
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Oficina TIC · Administración</div>
            <h1>Auditoría</h1>
            <p class="sub">Registro de todas las acciones realizadas en el sistema</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('adm.auditoria.export', $query) }}"><x-icon name="download" size="16" />Exportar</a>
            <x-notifications />
        </div>
    </div>

    <section class="card">
        <h2>Actividad reciente</h2>
        <p class="hint" style="margin-bottom:14px">{{ $total === 1 ? 'Última acción' : "Últimas {$total} acciones" }} · se conservan 5 años</p>

        <form class="filters" method="get" action="{{ route('adm.auditoria') }}">
            <div class="field">
                <label for="usuario">Usuario</label>
                <select id="usuario" name="usuario">
                    <option value="">Todos</option>
                    @foreach ($userOptions as $name)
                        <option @selected($filters['usuario'] === $name)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="modulo">Módulo</label>
                <select id="modulo" name="modulo">
                    <option value="">Todos</option>
                    @foreach (AdminStore::MODULES as $m)
                        <option @selected($filters['modulo'] === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="desde">Desde</label>
                <input id="desde" type="date" name="desde" value="{{ $filters['desde']->toDateString() }}">
            </div>
            <div class="field">
                <label for="hasta">Hasta</label>
                <input id="hasta" type="date" name="hasta" value="{{ $filters['hasta']->toDateString() }}">
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Filtrar</button>
                <a class="btn" href="{{ route('adm.auditoria') }}">Limpiar</a>
            </div>
        </form>

        @if ($rows->isEmpty())
            <p class="empty">No hay acciones con estos filtros.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Fecha y hora</th><th>Usuario</th><th>Rol</th><th>Acción</th><th>Detalle</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td style="white-space:nowrap">{{ date_es($r['at']) }} · {{ $r['at']->format('H:i') }}</td>
                            <td class="strong" style="white-space:nowrap">{{ $r['user'] }}</td>
                            <td class="muted">{{ $r['role'] }}</td>
                            <td><span class="badge {{ $r['tone'] }}">{{ $r['action'] }}</span></td>
                            <td>{{ $r['detail'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
