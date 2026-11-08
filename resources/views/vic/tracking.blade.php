@extends('layouts.app')
@section('title', 'Seguimiento técnico')
@section('content')
    @php
        use App\Http\Controllers\Vic\MonitoringController;
        $delivered = $all->where('status', 'entregado')->count();
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Vicerrectoría de Investigación y Extensión</div>
            <h1>Seguimiento técnico</h1>
            <p class="sub">Objetivos, productos, porcentaje de avance y control de fechas de entrega</p>
        </div>
        <div class="actions"><a class="btn btn-icon" href="{{ route('vic.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a></div>
    </div>

    <div class="grid-4">
        <div class="card stat"><span class="stat-label"><span class="stat-icon"><x-icon name="box" size="16" /></span>Productos</span><span class="stat-value">{{ $all->count() }}</span><span class="stat-foot">En proyectos activos</span></div>
        <div class="card stat"><span class="stat-label"><span class="stat-icon"><x-icon name="checkc" size="16" /></span>Entregados</span><span class="stat-value green">{{ $delivered }}</span><span class="stat-foot">{{ $all->count() ? round($delivered / $all->count() * 100) : 0 }}% del total</span></div>
        <div class="card stat"><span class="stat-label"><span class="stat-icon amber"><x-icon name="calendar" size="16" /></span>Próximos 30 días</span><span class="stat-value">{{ $all->where('status', 'proximo')->count() }}</span><span class="stat-foot">Entregas por vencer</span></div>
        <div class="card stat"><span class="stat-label"><span class="stat-icon red"><x-icon name="warning" size="16" /></span>Vencidos</span><span class="stat-value red">{{ $all->where('status', 'vencido')->count() }}</span><span class="stat-foot">Requieren atención</span></div>
    </div>

    <div class="cols" style="grid-template-columns:minmax(0,1fr) 340px">
        <section class="card">
            <div class="card-head" style="align-items:center">
                <h2>Entregables</h2>
                <nav class="tabs" aria-label="Filtrar entregables">
                    <a href="{{ route('vic.seguimiento') }}" @class(['on' => ! $filter])>Todos ({{ $all->count() }})</a>
                    @foreach (MonitoringController::PRODUCT_FILTERS as $key => [$label, $status])
                        <a href="{{ route('vic.seguimiento', ['filtro' => $key]) }}" @class(['on' => $filter === $key])>{{ $label }} ({{ $all->where('status', $status)->count() }})</a>
                    @endforeach
                </nav>
            </div>
            @if ($list->isEmpty())
                <p class="empty">No hay entregables con este filtro.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Producto</th><th>Proyecto</th><th>Entrega</th><th>Avance</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                        <tbody>
                        @foreach ($list as $p)
                            <tr>
                                <td style="min-width:170px"><div class="title">{{ $p['name'] }}</div><div class="meta">{{ $p['type'] }} · {{ number_format($p['done'], 0, ',', '.') }}/{{ number_format($p['target'], 0, ',', '.') }} {{ $p['unit'] }}</div></td>
                                <td style="min-width:150px">{{ $p['project']->name }}</td>
                                <td style="white-space:nowrap">{{ date_es($p['due']) }}</td>
                                <td style="min-width:100px"><div class="strong" style="font-size:12px">{{ $p['progress'] }}%</div><div class="bar"><i class="{{ $p['status'] === 'vencido' ? 'red' : '' }}" style="width:{{ $p['progress'] }}%"></i></div></td>
                                <td><span class="badge {{ $p['tone'] }}">{{ $p['status_label'] }}</span></td>
                                <td><a class="btn btn-sm" href="{{ route('vic.proyectos.objetivos', $p['project']) }}">Revisar<span class="sr-only"> {{ $p['name'] }}</span></a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <h2>Avance técnico vs. tiempo</h2>
            <p class="hint" style="margin-bottom:14px">Barra = avance · línea = tiempo transcurrido</p>
            @foreach ($projects as $r)
                <div class="line-bar">
                    <a class="row-link" href="{{ route('vic.proyectos.objetivos', $r['project']) }}">{{ $r['project']->name }}</a><span class="strong">{{ $r['progress'] }}%</span>
                    <div class="bar time-bar" title="Avance {{ $r['progress'] }} % · tiempo transcurrido {{ $r['time'] }} %">
                        <i class="{{ $r['progress'] + 15 < $r['time'] ? 'red' : '' }}" style="width:{{ $r['progress'] }}%"></i>
                        <b style="left:{{ $r['time'] }}%" aria-hidden="true"></b>
                    </div>
                </div>
            @endforeach
        </section>
    </div>
@endsection
