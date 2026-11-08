@extends('layouts.app')
@section('title', 'Control financiero')
@section('content')
    @php $store = app(\App\Support\FinanceStore::class); @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Dirección Financiera</div>
            <h1>Control financiero</h1>
            <p class="sub">Compromisos, CDP y pagos de todos los proyectos · Corte: {{ date_es(today()) }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('fin.control.export') }}"><x-icon name="download" size="16" />Exportar</a>
            <a class="btn btn-icon" href="{{ route('fin.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon amber"><x-icon name="report" size="16" /></span>CDP por expedir</span>
            <span class="stat-value">{{ $cdp->count() }}</span>
            <span class="stat-foot">{{ money($cdp->sum('value')) }} aprobados por Vicerrectoría</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon blue"><x-icon name="money" size="16" /></span>Pagos pendientes</span>
            <span class="stat-value">{{ $payments->count() }}</span>
            <span class="stat-foot">{{ money($payments->sum('value')) }} por girar</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="trend" size="16" /></span>Ejecutado {{ today()->year }}</span>
            <span class="stat-value">{{ money_m($executed) }}</span>
            <span class="stat-foot">{{ $budget > 0 ? round($executed / $budget * 100) : 0 }} % del presupuesto asignado</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon red"><x-icon name="warning" size="16" /></span>Sobrecostos detectados</span>
            <span class="stat-value red">{{ $overrunProjects->count() }}</span>
            <span class="stat-foot">{{ $overrunProjects->map(fn ($p) => \Illuminate\Support\Str::of($p->name)->replace(['Monitoreo agrícola con drones', 'Gestión integral de residuos sólidos'], ['Monitoreo drones', 'Residuos sólidos']))->implode(' · ') ?: 'Sin sobrecostos' }}</span>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px">
        <div class="card-head">
            <div>
                <h2>Por gestionar</h2>
                <p class="hint">Solicitudes que ya aprobó Vicerrectoría y esperan acción financiera</p>
            </div>
            <a class="strong" href="{{ route('fin.compras') }}">Ver todas las compras</a>
        </div>
        @if ($items->isEmpty())
            <p class="empty">No hay CDP ni pagos pendientes.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Código</th><th>Concepto</th><th>Rubro</th><th class="num">Valor</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    @foreach ($items as $i)
                        <tr>
                            <td style="white-space:nowrap"><div class="title">{{ $i['label'] ?? $i['code'] }}</div><div class="meta">{{ $i['kind'] === 'cuota' ? 'Contrato' : date_es($i['date']) }}</div></td>
                            <td style="min-width:200px"><div class="title">{{ $i['concept'] }}</div><div class="meta">{{ $i['project']->name }}</div></td>
                            <td>{{ $i['line'] }}</td>
                            <td class="num strong">{{ money($i['value']) }}</td>
                            <td><span class="badge {{ $i['tone'] }}">{{ $i['state'] }}</span></td>
                            <td>
                                <div class="actions" style="flex-wrap:nowrap">
                                    @if ($i['kind'] === 'cdp')
                                        <a class="btn btn-sm btn-primary" href="{{ route('fin.compras', ['ver' => $i['code'], 'modal' => 'cdp']) }}">Expedir CDP</a>
                                        <a class="btn btn-sm" href="{{ route('fin.compras', ['ver' => $i['code'], 'modal' => 'devolver']) }}">Devolver</a>
                                    @else
                                        <a class="btn btn-sm btn-primary" href="{{ route('fin.pago.form', $i['code']) }}">Registrar pago</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Ejecutado vs. proyectado por proyecto</h2>
                <p class="hint">Desviación de la curva S al corte</p>
            </div>
            <a class="strong" href="{{ route('fin.presupuesto') }}">Ver control presupuestario</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Proyecto</th><th>Proyectado</th><th>Ejecutado</th><th class="num">Desviación</th></tr></thead>
                <tbody>
                @foreach ($totals as $t)
                    @php $dev = $t['executed_pct'] - $t['planned_pct']; @endphp
                    <tr>
                        <td><a class="row-link title" href="{{ route('fin.proyecto', $t['project']) }}">{{ $t['project']->name }}</a></td>
                        <td style="min-width:140px"><div class="strong" style="font-size:12px">{{ $t['planned_pct'] }} %</div><div class="bar"><i class="dark" style="width:{{ min(100, $t['planned_pct']) }}%"></i></div></td>
                        <td style="min-width:140px"><div class="strong" style="font-size:12px">{{ $t['executed_pct'] }} %</div><div class="bar"><i style="width:{{ min(100, $t['executed_pct']) }}%"></i></div></td>
                        <td class="num strong {{ $dev < 0 ? 'text-red' : '' }}">{{ $dev > 0 ? '+' : '' }}{{ $dev }} pts</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @isset($payItem)
        @include('fin.partials.payment-modal', ['item' => $payItem, 'close' => route('fin.control'), 'back' => 'control'])
    @endisset
@endsection
