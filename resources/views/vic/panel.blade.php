@extends('layouts.app')
@section('title', 'Panel general')
@section('content')
    <div class="page-head">
        <div>
            <div class="crumb">FET · Vicerrectoría de Investigación y Extensión</div>
            <h1>Panel general</h1>
            <p class="sub">Estado consolidado de todos los proyectos · Corte: {{ date_es(today()) }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('vic.proyectos.export', ['filtro' => 'activos']) }}"><x-icon name="download" size="16" />Exportar</a>
            <a class="btn btn-primary" href="{{ route('vic.proyectos.index', ['modal' => 'nuevo']) }}"><x-icon name="plus" size="16" />Nuevo proyecto</a>
            <x-notifications />
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="folder" size="16" /></span>Proyectos activos</span>
            <span class="stat-value">{{ $active->count() }}</span>
            <span class="stat-foot">{{ $byType['investigacion'] }} investigación · {{ $byType['extension'] }} extensión</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="wallet" size="16" /></span>Presupuesto total</span>
            <span class="stat-value">{{ money_m($total) }}</span>
            <span class="stat-foot">Asignado a proyectos activos</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon blue"><x-icon name="trend" size="16" /></span>Ejecutado</span>
            <span class="stat-value">{{ money_m($executed) }}</span>
            <span class="stat-foot">{{ $total > 0 ? round($executed / $total * 100) : 0 }}% del presupuesto · avance técnico prom. {{ $avgTechnical }}%</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon red"><x-icon name="warning" size="16" /></span>Alertas activas</span>
            <span class="stat-value">{{ $alertCount }}</span>
            <span class="stat-foot">{{ $criticalCount }} críticas · {{ $actionCount }} pendientes de tu gestión</span>
        </div>
    </div>

    <div class="cols">
        <div class="stack">
            <section class="card">
                <div class="card-head">
                    <div>
                        <h2>Proyectos en ejecución</h2>
                        <p class="hint">Avance técnico vs. ejecución presupuestaria · clic para ver el detalle</p>
                    </div>
                    <nav class="tabs" aria-label="Filtrar por tipo">
                        <a href="{{ route('vic.panel') }}" @class(['on' => ! $type])>Todos</a>
                        <a href="{{ route('vic.panel', ['tipo' => 'investigacion']) }}" @class(['on' => $type === 'investigacion'])>Investigación</a>
                        <a href="{{ route('vic.panel', ['tipo' => 'extension']) }}" @class(['on' => $type === 'extension'])>Extensión</a>
                    </nav>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Proyecto / contrato</th><th>Fase</th><th>Avance técnico</th><th>Ejecución $</th><th class="num">Valor</th><th>Estado</th></tr></thead>
                        <tbody>
                        @forelse ($list as $p)
                            @php $fin = $p->financialProgress(); @endphp
                            <tr>
                                <td style="min-width:200px">
                                    <a class="row-link title" href="{{ route('vic.proyectos.show', $p) }}">{{ $p->name }}</a>
                                    <div class="meta">{{ $p->contractLabel() }} {{ $p->code }} · {{ $p->entity }}</div>
                                </td>
                                <td><span class="badge yellow">{{ $p->phaseLabel() }}</span></td>
                                <td style="min-width:110px"><div class="strong" style="font-size:12px">{{ $p->technical_progress }}%</div><div class="bar"><i style="width:{{ $p->technical_progress }}%"></i></div></td>
                                <td style="min-width:110px"><div class="strong" style="font-size:12px">{{ $fin }}%</div><div class="bar"><i class="{{ $p->health === 'sobrecosto' ? 'red' : 'dark' }}" style="width:{{ min(100, $fin) }}%"></i></div></td>
                                <td class="num">{{ money_m($p->value) }}</td>
                                <td><span class="badge {{ $p->statusTone() }}">{{ $p->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty">No hay proyectos activos de este tipo.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <div class="card-head">
                    <div>
                        <h2>Curva S consolidada</h2>
                        <p class="hint">Presupuesto proyectado vs. ejecutado de todos los proyectos activos</p>
                    </div>
                    <div class="legend"><span><i class="dash"></i>Proyectado</span><span><i></i>Ejecutado</span><span><i class="today"></i>Hoy</span></div>
                </div>
                @if ($curve)
                    <x-s-curve :curve="$curve" label="Curva S consolidada" />
                @else
                    <p class="empty">No hay proyectos activos.</p>
                @endif
            </section>
        </div>

        <div class="stack">
            <section class="card">
                <h2 style="margin-bottom:14px">Proyectos por fase</h2>
                @foreach ($byPhase as $ph)
                    <div style="margin-bottom:12px">
                        <div style="display:flex;justify-content:space-between;margin-bottom:5px"><span>{{ $ph['label'] }}</span><b>{{ $ph['count'] }}</b></div>
                        <div class="bar"><i class="dark" style="width:{{ $active->count() ? $ph['count'] / $active->count() * 100 : 0 }}%"></i></div>
                    </div>
                @endforeach
                @php
                    $n = max(1, $active->count());
                    $ext = $byType['extension'] / $n;
                    $c = 2 * M_PI * 36;
                @endphp
                <div style="display:flex;align-items:center;gap:18px;margin-top:16px">
                    <svg width="96" height="96" viewBox="0 0 96 96" role="img" aria-label="{{ $byType['extension'] }} de extensión y {{ $byType['investigacion'] }} de investigación">
                        <circle cx="48" cy="48" r="36" fill="none" stroke="#efe7a6" stroke-width="16" />
                        <circle cx="48" cy="48" r="36" fill="none" stroke="#137a26" stroke-width="16" stroke-dasharray="{{ $c * $ext }} {{ $c }}" transform="rotate(-90 48 48)" />
                        <text x="48" y="50" text-anchor="middle" style="font-size:20px;font-weight:700;fill:#17201b">{{ $active->count() }}</text>
                        <text x="48" y="64" text-anchor="middle" style="font-size:10px;fill:#5b655f">total</text>
                    </svg>
                    <div style="flex:1;display:grid;gap:8px;font-size:13px">
                        <div style="display:flex;justify-content:space-between"><span><i style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#137a26;margin-right:6px"></i>Extensión</span><b>{{ $byType['extension'] }}</b></div>
                        <div style="display:flex;justify-content:space-between"><span><i style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#efe7a6;margin-right:6px"></i>Investigación</span><b>{{ $byType['investigacion'] }}</b></div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="card-head" style="align-items:center">
                    <h2>Alertas recientes</h2>
                    <a href="{{ route('vic.alertas') }}" class="strong">Ver todas</a>
                </div>
                <div class="stack" style="gap:8px">
                    @forelse ($recentAlerts as $a)
                        <x-alert-item :alert="$a" :href="route('vic.proyectos.show', $a->project_id)" />
                    @empty
                        <p class="muted">Sin alertas críticas.</p>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="card-head" style="align-items:center">
                    <h2>Pendientes de tu gestión</h2>
                    <a href="{{ route('vic.aprobaciones', ['tipo' => 'compra']) }}" class="strong">Compras</a>
                </div>
                <div class="stack" style="gap:8px">
                    @foreach ($purchases as $c)
                        <a href="{{ route('vic.compras', ['ver' => $c->code]) }}" class="row-link" style="display:flex;gap:10px;align-items:flex-start;border:1px solid var(--line);border-radius:10px;padding:10px 12px">
                            <x-icon name="cart" size="16" style="color:var(--green-700);margin-top:2px;flex:none" />
                            <span style="flex:1;min-width:0">
                                <span class="title" style="display:block;color:var(--green-700)">{{ $c->code }} · {{ $c->description }}</span>
                                <span class="meta">{{ money($c->value) }}</span>
                            </span>
                            <span class="badge {{ $c->statusTone() }}">{{ $c->statusLabel() }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
@endsection
