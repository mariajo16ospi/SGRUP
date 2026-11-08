@extends('layouts.app')
@section('title', 'Gestión de compras')
@section('content')
    @php
        use App\Support\ApprovalInbox;
        $sel = $selected;
        $q = fn (array $extra = []) => route('vic.compras', array_filter(['estado' => $tab === 'todas' ? null : $tab, 'ver' => $sel->code] + $extra));
        $quoted = (float) ($sel->original_value ?? $sel->value);
        $diff = (float) $sel->value - $quoted;
        $vicDone = ! in_array($sel->status, ['en_vicerrectoria', 'requiere_ajuste', 'rechazada'], true);
        $flow = [
            ['Investigador / Coordinador', 'Creó la solicitud · ' . $sel->requester->name . ' · ' . date_es($sel->submitted_at), 'done'],
            ['Vicerrectoría / Subdirección', match ($sel->status) {
                'en_vicerrectoria' => 'Te corresponde: revisión técnica', 'rechazada' => 'Rechazada', 'requiere_ajuste' => 'Devuelta para ajuste', default => 'Aprobada',
            }, $sel->status === 'en_vicerrectoria' ? 'current' : ($sel->status === 'rechazada' ? 'error' : ($vicDone ? 'done' : 'next'))],
            ['Área financiera', $sel->cdp ? "CDP {$sel->cdp} expedido" : 'Valida disponibilidad y expide CDP', $sel->cdp || $sel->status === 'pagada' ? 'done' : ($sel->status === 'en_financiera' ? 'current' : 'next')],
            ['Pago', $sel->status === 'pagada' ? 'Pago registrado' : 'Registro del gasto y soporte', $sel->status === 'pagada' ? 'done' : 'next'],
        ];
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Vicerrectoría de Investigación y Extensión</div>
            <h1>Gestión de compras</h1>
            <p class="sub">Solicitudes con aprobación por roles, validación de presupuesto y trazabilidad de cada gasto</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('vic.reportes.export', 'compras') }}"><x-icon name="download" size="16" />Exportar</a>
            <a class="btn btn-icon" href="{{ route('vic.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat"><span class="stat-label">Solicitudes del mes</span><span class="stat-value">{{ $ofMonth->count() }}</span><span class="stat-foot">{{ $pendingOfMonth }} en aprobación</span></div>
        <div class="card stat"><span class="stat-label">Monto solicitado</span><span class="stat-value">{{ money_m($ofMonth->sum('value')) }}</span><span class="stat-foot">{{ month_long($month) }}</span></div>
        <div class="card stat"><span class="stat-label">Aprobado / pagado</span><span class="stat-value green">{{ money_m($approved->sum('value')) }}</span><span class="stat-foot">{{ $approved->count() }} solicitudes</span></div>
        <div class="card stat"><span class="stat-label">Pendientes de tu gestión</span><span class="stat-value">{{ $mine }}</span><span class="stat-foot">Esperan tu aprobación</span></div>
    </div>

    <div class="split">
        <section class="card">
            <h2>Solicitudes de compra</h2>
            <p class="flow-hint">Flujo: Coordinador → Vicerrectoría → Área financiera → Pago</p>
            <nav class="chips" aria-label="Filtrar por estado">
                @foreach ($tabs as $key => $t)
                    <a href="{{ route('vic.compras', array_filter(['estado' => $key === 'todas' ? null : $key])) }}" @class(['on' => $tab === $key])>{{ $t['label'] }} ({{ $t['count'] }})</a>
                @endforeach
            </nav>
            @if ($list->isEmpty())
                <p class="empty">No hay solicitudes en este estado.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Código</th><th>Descripción</th><th class="num">Valor</th><th>Solicitante</th><th>Estado</th></tr></thead>
                        <tbody>
                        @foreach ($list as $p)
                            <tr @class(['selected' => $p->code === $sel->code])>
                                <td style="white-space:nowrap">
                                    <a class="title" href="{{ route('vic.compras', array_filter(['estado' => $tab === 'todas' ? null : $tab, 'ver' => $p->code])) }}" @if($p->code === $sel->code) aria-current="true" @endif>{{ $p->code }}</a>
                                    <div class="meta">{{ date_es($p->submitted_at) }}</div>
                                </td>
                                <td style="min-width:200px"><div class="title">{{ $p->description }}</div><div class="meta">{{ $p->project->name }} · {{ $p->budgetLine->name }}</div></td>
                                <td class="num"><div class="strong">{{ money($p->value) }}</div>@if ($p->overrunPercent())<div class="meta text-red">Cotizado {{ money_m($p->original_value) }}</div>@endif</td>
                                <td style="white-space:nowrap"><span class="user-cell"><span class="avatar" aria-hidden="true">{{ $p->requester->initials() }}</span>{{ $p->requester->name }}</span></td>
                                <td>
                                    <span class="badge {{ $p->statusTone() }}">{{ $p->statusLabel() }}</span>
                                    @if ($p->overrunPercent() && $p->status === 'en_vicerrectoria')<div><span class="badge red" style="margin-top:4px">Excede rubro</span></div>@endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <aside class="card detail" aria-label="Detalle de la solicitud">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                <span class="eyebrow-sm">Solicitud {{ $sel->code }}</span>
                <span class="badge {{ $sel->statusTone() }}">{{ $sel->statusLabel() }}</span>
            </div>
            <h3>{{ $sel->description }}</h3>
            <p class="muted" style="font-size:13px">Proyecto: <a href="{{ route('vic.proyectos.compras', $sel->project) }}">{{ $sel->project->name }}</a> · Rubro: {{ $sel->budgetLine->name }}</p>

            <div class="box">
                <h4>Validación de presupuesto</h4>
                <dl class="kv">
                    <dt>Asignado al rubro {{ $sel->budgetLine->name }}</dt><dd>{{ money($line['budgeted']) }}</dd>
                    <dt>Ya ejecutado</dt><dd>{{ money($line['executed']) }}</dd>
                    <dt>Comprometido (CDP sin pagar)</dt><dd>{{ money($line['committed']) }}</dd>
                    <dt class="strong">Disponible</dt><dd @class(['text-red' => $insufficient])>{{ money($line['available']) }}</dd>
                    <dt>Valor cotizado inicialmente</dt><dd>{{ money($quoted) }}</dd>
                    <dt>Valor de esta solicitud</dt><dd>{{ money($sel->value) }}</dd>
                    <dt>Diferencia</dt><dd @class(['text-red' => $diff > 0])>{{ $diff > 0 ? '+ ' : '' }}{{ money($diff) }}</dd>
                </dl>
            </div>
            @if ($sel->overrunPercent())
                <div class="alert" style="margin-top:12px"><x-icon name="trend" size="16" /><div class="alert-body"><div class="alert-title">Sobrecosto: +{{ money($diff) }} (+{{ $sel->overrunPercent() }}%)</div><div class="alert-detail">Cotizado en {{ money($quoted) }}, valor actual {{ money($sel->value) }}.</div></div></div>
            @endif
            @if ($insufficient)
                <div class="alert warn" style="margin-top:8px"><x-icon name="wallet" size="16" /><div class="alert-body"><div class="alert-title">Presupuesto insuficiente</div><div class="alert-detail">Se requiere un traslado presupuestal aprobado por Vicerrectoría antes de la validación financiera.</div></div></div>
                <a class="btn btn-sm" style="margin-top:8px" href="{{ route('vic.proyectos.presupuesto', [$sel->project, 'modal' => 'traslado']) }}"><x-icon name="refresh" size="14" />Hacer traslado presupuestal</a>
            @endif

            <div class="box">
                <h4>Flujo de aprobación</h4>
                <ol class="steps">
                    @foreach ($flow as $i => [$who, $text, $state])
                        <li><span class="step {{ $state }}">@if($state === 'done')<x-icon name="check" size="12" />@else{{ $i + 1 }}@endif</span>
                            <span><b>{{ $who }}</b><span @class(['meta', 'current-text' => $state === 'current'])>{{ $text }}</span></span></li>
                    @endforeach
                </ol>
            </div>

            <div class="box">
                <h4>Historial</h4>
                <ul class="history">
                    <li><b>Creó la solicitud</b> · {{ $sel->requester->name }} · {{ date_es($sel->submitted_at) }}
                        @if ($sel->overrunPercent())<q>El proveedor actualizó la cotización: pasó de {{ money($quoted) }} a {{ money($sel->value) }}.</q>@endif</li>
                    @foreach ($sel->changes_log as $log)
                        <li><b>{{ $log['text'] }}</b> · {{ $log['by'] }} · {{ date_es(\Illuminate\Support\Carbon::parse($log['at'])) }}</li>
                    @endforeach
                </ul>
            </div>

            @if ($sel->status === 'en_vicerrectoria')
                <div class="detail-actions">
                    <a class="btn btn-danger" href="{{ $q(['modal' => 'rechazar']) }}">Rechazar</a>
                    <a class="btn" href="{{ $q(['modal' => 'ajuste']) }}">Solicitar ajuste</a>
                    <a class="btn btn-primary" href="{{ $q(['modal' => 'aprobar']) }}">Aprobar</a>
                </div>
            @endif
        </aside>
    </div>

    @if ($modal === 'aprobar')
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="ap-title" method="post" action="{{ route('vic.aprobaciones.aprobar', ['compra', $sel->id]) }}">
                @csrf
                <input type="hidden" name="back" value="{{ $q() }}">
                <div class="modal-head">
                    <div><h2 id="ap-title">Aprobar {{ $sel->code }}</h2><p>{{ $sel->description }} · {{ money($sel->value) }}</p></div>
                    <a class="btn btn-icon" href="{{ $q() }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    @if ($insufficient)
                        <p class="alert" style="margin:0"><span class="alert-body">El rubro no tiene saldo suficiente: la aprobación será rechazada por el control presupuestal. Haz primero el traslado.</span></p>
                    @endif
                    <div class="field">
                        <label for="comment">Comentario (opcional)</label>
                        <textarea id="comment" name="comment">{{ old('comment') }}</textarea>
                    </div>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $q() }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Aprobar y enviar a financiera</button>
                </div>
            </form>
        </div>
    @elseif ($modal)
        @php $reject = $modal === 'rechazar'; @endphp
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="dc-title" method="post" action="{{ route('vic.compras.decidir', $sel->code) }}">
                @csrf
                <input type="hidden" name="decision" value="{{ $reject ? 'rechazar' : 'ajuste' }}">
                <div class="modal-head">
                    <div><h2 id="dc-title">{{ $reject ? 'Rechazar' : 'Devolver' }} {{ $sel->code }}{{ $reject ? '' : ' para ajuste' }}</h2><p>{{ $sel->description }} · {{ $sel->project->name }}</p></div>
                    <a class="btn btn-icon" href="{{ $q() }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    @if ($reject)
                        <div class="alert" style="margin:0"><x-icon name="warning" size="16" /><div class="alert-body"><div class="alert-title">Esta acción es definitiva</div><div class="alert-detail">La solicitud se cierra y queda registrada en la trazabilidad del proyecto. El investigador deberá crear una nueva solicitud.</div></div></div>
                    @else
                        <p class="alert warn" style="margin:0"><span class="alert-body">La solicitud vuelve a {{ $sel->requester->name }} con tus observaciones. No se compromete presupuesto hasta que la reenvíe.</span></p>
                    @endif
                    <div class="field">
                        <label for="reason">Motivo {{ $reject ? 'del rechazo' : 'del ajuste' }} *</label>
                        <select id="reason" name="reason">
                            @foreach ($reject ? ApprovalInbox::REJECT_REASONS : ApprovalInbox::ADJUST_REASONS as $r)<option @selected(old('reason') === $r)>{{ $r }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="notes">{{ $reject ? 'Justificación' : 'Observaciones para el investigador' }} *</label>
                        <textarea id="notes" name="notes" required placeholder="{{ $reject ? 'Escribe la justificación del rechazo…' : 'Ej.: adjuntar dos cotizaciones adicionales o solicitar traslado presupuestal.' }}">{{ old('notes') }}</textarea>
                        @error('notes')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    @unless ($reject)
                        <label class="check"><input type="checkbox" name="notify" value="1" checked>Notificar por correo a {{ $sel->requester->email }}</label>
                    @endunless
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $q() }}">Cancelar</a>
                    <button class="btn {{ $reject ? 'btn-danger' : 'btn-primary' }}" type="submit">{{ $reject ? 'Rechazar solicitud' : 'Devolver al investigador' }}</button>
                </div>
            </form>
        </div>
    @endif
@endsection
