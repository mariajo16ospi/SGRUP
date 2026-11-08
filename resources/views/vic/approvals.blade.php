@extends('layouts.app')
@section('title', 'Bandeja de aprobaciones')
@section('content')
    <div class="page-head">
        <div>
            <div class="crumb">FET · Vicerrectoría de Investigación y Extensión</div>
            <h1>Bandeja de aprobaciones</h1>
            <p class="sub">{{ $total }} {{ $total === 1 ? 'solicitud espera' : 'solicitudes esperan' }} tu decisión · ordenadas de la más antigua a la más reciente</p>
        </div>
        <div class="actions">
            <button class="btn" type="button" disabled title="Disponible en la siguiente fase">Exportar</button>
            <a class="btn btn-icon" href="{{ route('vic.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label">Compras por aprobar</span>
            <span class="stat-value">{{ $purchases->count() }}</span>
            <span class="stat-foot">{{ money($purchases->sum('value')) }} en total</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Avances mensuales por revisar</span>
            <span class="stat-value green">{{ $progress->count() }}</span>
            <span class="stat-foot">Habilitan pagos por {{ money_m($progress->sum('value')) }}</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Traslados presupuestales</span>
            <span class="stat-value">{{ $transfers->count() }}</span>
            <span class="stat-foot">{{ $transfers->isEmpty() ? 'Sin traslados pendientes' : $transferSummary }}</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Vencen esta semana</span>
            <span class="stat-value {{ $overdue ? 'red' : '' }}">{{ $overdue }}</span>
            <span class="stat-foot">Plazo de respuesta: 5 días hábiles</span>
        </div>
    </div>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Solicitudes pendientes</h2>
                <p class="hint">Revisa el detalle antes de aprobar. Todo queda en la trazabilidad del proyecto.</p>
            </div>
        </div>
        <nav class="chips" aria-label="Filtrar por tipo">
            <a href="{{ route('vic.aprobaciones') }}" @class(['on' => ! $filter]) @if(! $filter) aria-current="true" @endif>Todas ({{ $total }})</a>
            @foreach ($tabs as $t)
                <a href="{{ route('vic.aprobaciones', ['tipo' => $t['key']]) }}" @class(['on' => $filter === $t['key']]) @if($filter === $t['key']) aria-current="true" @endif>{{ $t['label'] }} ({{ $t['count'] }})</a>
            @endforeach
        </nav>

        @if ($items->isEmpty())
            <p class="empty">No hay solicitudes pendientes{{ $filter ? ' de este tipo' : '' }}.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Tipo</th><th>Solicitud</th><th>Proyecto</th><th>Solicitante</th><th>Valor</th><th>Recibida</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><span class="badge {{ $item['tone'] }}">{{ $item['label'] }}</span></td>
                            <td style="min-width:220px">
                                <div class="title">{{ $item['title'] }}</div>
                                @if ($item['subtitle'])<div class="meta">{{ $item['subtitle'] }}</div>@endif
                                @if ($item['flag'])<span class="badge red" style="margin-top:4px">{{ $item['flag'] }}</span>@endif
                            </td>
                            <td class="title" style="min-width:160px">{{ $item['project']?->name ?? '—' }}</td>
                            <td style="white-space:nowrap">{{ $item['requester'] }}</td>
                            <td class="strong" style="white-space:nowrap">{{ money($item['value']) }}</td>
                            <td @class(['text-red strong' => $item['overdue']]) style="white-space:nowrap">{{ days_ago($item['received']) }}</td>
                            <td>
                                <div class="actions" style="flex-wrap:nowrap">
                                    @if ($item['review_url'])
                                        <a class="btn btn-sm" href="{{ $item['review_url'] }}">Revisar</a>
                                    @else
                                        <button class="btn btn-sm" type="button" disabled title="Disponible en la siguiente fase">Revisar</button>
                                    @endif
                                    <form method="post" action="{{ route('vic.aprobaciones.aprobar', [$item['type'], $item['id']]) }}"
                                          onsubmit="return confirm('¿Aprobar {{ e(addslashes($item['title'])) }}?')">
                                        @csrf
                                        <button class="btn btn-sm btn-primary" type="submit">Aprobar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
