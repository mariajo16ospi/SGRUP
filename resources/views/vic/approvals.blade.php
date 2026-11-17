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
            <a class="btn" href="{{ route('vic.aprobaciones.export', array_filter(['tipo' => $filter])) }}"><x-icon name="download" size="16" />Exportar</a>
            <x-notifications />
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
                                    <a class="btn btn-sm" href="{{ $item['review_url'] }}">Revisar</a>
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

    @if ($review)
        @php
            $p = $review['project'];
            $close = route('vic.aprobaciones', array_filter(['tipo' => $filter]));
        @endphp
        <div class="modal-backdrop">
            <div class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="rv-title">
                <div class="modal-head">
                    <div>
                        <h2 id="rv-title">{{ $review['rejecting'] ? 'Rechazar propuesta' : 'Proyecto propuesto' }} · {{ $p->code }}</h2>
                        <p>{{ $p->name }} · propuesto por {{ $p->coordinator->name }} {{ days_ago($p->created_at) }}</p>
                    </div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                @if ($review['rejecting'])
                    <form method="post" action="{{ route('vic.aprobaciones.rechazar', $p->id) }}">
                        @csrf
                        <input type="hidden" name="_form" value="rechazar-proyecto">
                        <input type="hidden" name="project_id" value="{{ $p->id }}">
                        <div class="modal-body">
                            <div class="field">
                                <label for="reason">Motivo del rechazo *</label>
                                <textarea id="reason" name="reason" required autofocus placeholder="Explica al coordinador por qué no se aprueba la propuesta">{{ old('reason') }}</textarea>
                                @error('reason')<span class="error">{{ $message }}</span>@enderror
                            </div>
                            <p class="modal-note">La propuesta sale de la bandeja y el motivo queda en la trazabilidad.</p>
                        </div>
                        <div class="modal-foot">
                            <a class="btn" href="{{ route('vic.aprobaciones', ['revisar' => $p->id]) }}">Volver</a>
                            <button class="btn btn-danger" type="submit">Rechazar propuesta</button>
                        </div>
                    </form>
                @else
                    <div class="modal-body">
                        <div class="grid-2">
                            <dl class="kv">
                                <dt>Tipo</dt><dd>{{ $p->typeLabel() }} · {{ $p->modalityLabel() }}</dd>
                                <dt>Entidad</dt><dd>{{ $p->entity }}</dd>
                                <dt>Coordinador</dt><dd>{{ $p->coordinator->name }}</dd>
                            </dl>
                            <dl class="kv">
                                <dt>Valor total</dt><dd>{{ money($p->value) }}</dd>
                                <dt>Vigencia</dt><dd>{{ date_es($p->start_date) }} – {{ date_es($p->end_date) }}</dd>
                                <dt>Duración</dt><dd>{{ (int) round($p->start_date->diffInMonths($p->end_date)) }} meses</dd>
                            </dl>
                        </div>
                        <div>
                            <h3 class="section-title">Objetivo general</h3>
                            <p style="margin-top:6px">{{ $p->general_objective }}</p>
                        </div>
                        <div>
                            <h3 class="section-title">Presupuesto por rubros</h3>
                            <div class="table-wrap">
                                <table>
                                    <thead><tr><th>Rubro</th><th class="num">Asignado</th><th class="num">%</th></tr></thead>
                                    <tbody>
                                    @foreach ($review['lines'] as $l)
                                        <tr><td>{{ $l['name'] }}</td><td class="num">{{ money($l['budgeted']) }}</td><td class="num">{{ $p->value > 0 ? round($l['budgeted'] / $p->value * 100) : 0 }} %</td></tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @if ($review['products']->isNotEmpty())
                            <div>
                                <h3 class="section-title">Productos</h3>
                                <ul style="margin:6px 0 0 18px">
                                    @foreach ($review['products'] as $prod)
                                        <li>{{ $prod['name'] }} · {{ $prod['target'] }} {{ $prod['unit'] }} · entrega {{ date_es($prod['due']) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <p class="modal-note">Al aprobarlo, el proyecto queda activo en fase de Preinversión.</p>
                    </div>
                    <div class="modal-foot">
                        <a class="btn" href="{{ $close }}">Cerrar</a>
                        <a class="btn btn-danger" href="{{ route('vic.aprobaciones', ['revisar' => $p->id, 'accion' => 'rechazar']) }}">Rechazar</a>
                        <form method="post" action="{{ route('vic.aprobaciones.aprobar', ['proyecto', $p->id]) }}">
                            @csrf
                            <input type="hidden" name="back" value="{{ $close }}">
                            <button class="btn btn-primary" type="submit">Aprobar proyecto</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
