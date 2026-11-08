@extends('layouts.app')
@section('title', 'Control presupuestario')
@section('content')
    @php $deviation = $executed - $planned; @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Dirección Financiera</div>
            <h1>Control presupuestario</h1>
            <p class="sub">Ejecución financiera, curva S y detección de sobrecostos</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('fin.presupuesto.export') }}"><x-icon name="download" size="16" />Exportar</a>
            <a class="btn btn-icon" href="{{ route('fin.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="wallet" size="16" /></span>Presupuesto activo</span>
            <span class="stat-value">{{ money_m($budget) }}</span>
            <span class="stat-foot">{{ $totals->count() }} proyectos</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon blue"><x-icon name="trend" size="16" /></span>Ejecutado</span>
            <span class="stat-value">{{ money_m($executed) }}</span>
            <span class="stat-foot">{{ $budget > 0 ? round($executed / $budget * 100) : 0 }} % del total</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon gray"><x-icon name="calendar" size="16" /></span>Planeado a la fecha</span>
            <span class="stat-value">{{ money_m($planned) }}</span>
            <span class="stat-foot @if($deviation < 0) text-red @endif">Desviación: {{ $deviation < 0 ? '-' : '' }}{{ money_m(abs($deviation)) }}</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon amber"><x-icon name="report" size="16" /></span>Comprometido</span>
            <span class="stat-value">{{ money_m($committed) }}</span>
            <span class="stat-foot">Compras con CDP por pagar</span>
        </div>
    </div>

    <div class="cols" style="grid-template-columns:minmax(0,1fr) 340px;margin-bottom:20px">
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Curva S institucional</h2>
                    <p class="hint">Suma de todos los proyectos activos</p>
                </div>
                <div class="legend"><span><i class="dash"></i>Proyectado</span><span><i></i>Ejecutado</span><span><i class="today"></i>Hoy</span></div>
            </div>
            <x-s-curve :curve="$curve" label="Curva S institucional" />
        </section>
        <section class="card">
            <h2 style="margin-bottom:14px">Ejecución por rubro</h2>
            @foreach ($byLine as $l)
                @php $pct = $l['budgeted'] > 0 ? $l['executed'] / $l['budgeted'] * 100 : 0; @endphp
                <div class="line-bar">
                    <span>{{ $l['name'] }}</span><span class="strong">{{ money_m($l['executed']) }} / {{ money_m($l['budgeted']) }}</span>
                    <div class="bar"><i class="{{ $pct > 100 ? 'red' : '' }}" style="width:{{ min(100, $pct) }}%"></i></div>
                </div>
            @endforeach
        </section>
    </div>

    <section class="card" style="margin-bottom:20px">
        <h2>Sobrecostos detectados</h2>
        <p class="hint" style="margin-bottom:12px">Compras cuyo valor superó lo cotizado y rubros que superan lo asignado</p>
        @if ($overruns->isEmpty())
            <p class="empty">No hay sobrecostos.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Proyecto</th><th>Concepto</th><th class="num">Previsto</th><th class="num">Actual</th><th class="num">Variación</th><th>Estado</th></tr></thead>
                    <tbody>
                    @foreach ($overruns as $o)
                        <tr>
                            <td><a class="row-link title" href="{{ route('fin.proyecto', $o['project']) }}">{{ $o['project']->name }}</a></td>
                            <td>{{ $o['concept'] }}</td>
                            <td class="num">{{ money($o['planned']) }}</td>
                            <td class="num strong">{{ money($o['actual']) }}</td>
                            <td class="num strong text-red">+{{ $o['pct'] }}%</td>
                            <td><span class="badge {{ $o['tone'] }}">{{ $o['state'] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <h2 style="margin-bottom:12px">Ejecución por proyecto</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Proyecto</th><th class="num">Presupuesto</th><th class="num">Planeado a la fecha</th><th class="num">Ejecutado</th><th class="num">Comprometido</th><th class="num">Disponible</th><th>Ejecución</th></tr></thead>
                <tbody>
                @foreach ($totals as $t)
                    <tr>
                        <td><a class="row-link title" href="{{ route('fin.proyecto', $t['project']) }}">{{ $t['project']->name }}</a></td>
                        <td class="num">{{ money($t['value']) }}</td>
                        <td class="num">{{ money($t['planned']) }}</td>
                        <td class="num strong">{{ money($t['executed']) }}</td>
                        <td class="num">{{ money($t['committed']) }}</td>
                        <td class="num">{{ money($t['available']) }}</td>
                        <td style="min-width:110px"><div class="strong" style="font-size:12px">{{ $t['executed_pct'] }}%</div><div class="bar"><i style="width:{{ min(100, $t['executed_pct']) }}%"></i></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
