@extends('layouts.app')
@section('title', 'Validación de compras')
@section('content')
    @php
        use App\Support\FinanceStore;
        $sel = $selected;
        $q = fn (array $extra = []) => route('fin.compras', array_filter(['estado' => $tab === 'todas' ? null : $tab, 'ver' => $sel->code] + $extra));
        $diff = $sel->original_value ? (float) $sel->value - (float) $sel->original_value : 0;
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Dirección Financiera</div>
            <h1>Validación de compras</h1>
            <p class="sub">Solicitudes aprobadas por Vicerrectoría: expide el CDP y registra el pago</p>
        </div>
        <div class="actions">
            <a class="btn btn-icon" href="{{ route('fin.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label">Solicitudes del mes</span>
            <span class="stat-value">{{ $ofMonth->count() }}</span>
            <span class="stat-foot">{{ $pendingOfMonth }} en aprobación</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Monto solicitado</span>
            <span class="stat-value">{{ money_m($ofMonth->sum('value')) }}</span>
            <span class="stat-foot">{{ month_long($month) }}</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Aprobado / pagado</span>
            <span class="stat-value green">{{ money_m($approved->sum('value')) }}</span>
            <span class="stat-foot">{{ $approved->count() }} {{ $approved->count() === 1 ? 'solicitud' : 'solicitudes' }}</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Pendientes de tu gestión</span>
            <span class="stat-value">{{ $actionable }}</span>
            <span class="stat-foot">CDP y pagos por gestionar</span>
        </div>
    </div>

    <div class="split">
        <section class="card">
            <h2>Solicitudes de compra</h2>
            <p class="flow-hint">Flujo: Coordinador → Vicerrectoría → Área financiera → Pago</p>
            <nav class="chips" aria-label="Filtrar por estado">
                @foreach ($tabs as $key => $t)
                    <a href="{{ route('fin.compras', array_filter(['estado' => $key === 'todas' ? null : $key])) }}" @class(['on' => $tab === $key])>{{ $t['label'] }} ({{ $t['count'] }})</a>
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
                                    <a class="title" href="{{ route('fin.compras', array_filter(['estado' => $tab === 'todas' ? null : $tab, 'ver' => $p->code])) }}" @if($p->code === $sel->code) aria-current="true" @endif>{{ $p->code }}</a>
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
                @if ($store->needsCdp($sel))<span class="badge amber">Requiere CDP</span>
                @elseif ($store->needsPayment($sel))<span class="badge green">{{ $sel->cdp }}</span>
                @else<span class="badge {{ $sel->statusTone() }}">{{ $sel->statusLabel() }}</span>@endif
            </div>
            <h3>{{ $sel->description }}</h3>
            <p class="muted" style="font-size:13px">Proyecto: {{ $sel->project->name }} · Rubro: {{ $sel->budgetLine->name }}</p>

            <div class="box">
                <h4>Validación de presupuesto</h4>
                <dl class="kv">
                    <dt>Asignado al rubro</dt><dd>{{ money($line['budgeted']) }}</dd>
                    <dt>Ya ejecutado</dt><dd>{{ money($line['executed']) }}</dd>
                    <dt>Comprometido (CDP sin pagar)</dt><dd>{{ money($line['committed']) }}</dd>
                    <dt class="strong">Disponible</dt><dd @class(['text-red' => $line['available'] < $sel->value && $store->needsCdp($sel)])>{{ money($line['available']) }}</dd>
                    <dt>Valor cotizado inicialmente</dt><dd>{{ money($sel->original_value ?? $sel->value) }}</dd>
                    <dt>Valor de esta solicitud</dt><dd>{{ money($sel->value) }}</dd>
                    <dt>Diferencia</dt><dd @class(['text-red' => $diff > 0])>{{ money($diff) }}</dd>
                </dl>
            </div>

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
                    @foreach ($history as $h)
                        <li><b>{{ $h['text'] }}</b> · {{ $h['by'] }} · {{ date_es($h['at']) }}@if ($h['note'])<q>{{ $h['note'] }}</q>@endif</li>
                    @endforeach
                </ul>
            </div>

            @if ($store->needsCdp($sel))
                <div class="detail-actions">
                    <a class="btn" href="{{ $q(['modal' => 'devolver']) }}">Devolver a Vicerrectoría</a>
                    <a class="btn btn-primary" href="{{ $q(['modal' => 'cdp']) }}">Expedir CDP</a>
                </div>
            @elseif ($store->needsPayment($sel))
                <div class="detail-actions"><a class="btn btn-primary" href="{{ $q(['modal' => 'pago']) }}">Registrar pago</a></div>
            @endif
        </aside>
    </div>

    @if ($modal === 'cdp')
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="cdp-title" method="post" action="{{ route('fin.compras.cdp', $sel->code) }}">
                @csrf
                <div class="modal-head">
                    <div>
                        <h2 id="cdp-title">Expedir CDP · {{ $sel->code }}</h2>
                        <p>{{ $sel->description }} · aprobado por Vicerrectoría</p>
                    </div>
                    <a class="btn btn-icon" href="{{ $q() }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    @error('cdp')<div class="flash err" role="alert" style="margin:0">{{ $message }}</div>@enderror
                    <div class="box" style="margin-top:0">
                        <dl class="kv">
                            <dt>Rubro</dt><dd>{{ $sel->budgetLine->name }}</dd>
                            <dt>Disponible en el rubro</dt><dd>{{ money($line['available']) }}</dd>
                            <dt>Valor de la solicitud</dt><dd>{{ money($sel->value) }}</dd>
                            <dt class="strong">Saldo después del CDP</dt><dd @class(['text-red' => $line['available'] < $sel->value])>{{ money($line['available'] - $sel->value) }}</dd>
                        </dl>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label for="cdp_number">N.º de CDP *</label>
                            <input id="cdp_number" name="cdp_number" value="{{ old('cdp_number', $store->nextCdpNumber()) }}" required>
                            @error('cdp_number')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="cdp_date">Fecha de expedición *</label>
                            <input id="cdp_date" name="cdp_date" type="date" value="{{ old('cdp_date', today()->toDateString()) }}" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="source">Fuente de financiación *</label>
                        <input id="source" name="source" value="{{ old('source', $sel->project->contractLabel() . ' ' . $sel->project->code . ' · ' . $sel->project->entity) }}" required>
                        @error('source')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <p class="modal-note">El CDP queda asociado a la solicitud y el valor pasa a «Comprometido» en el control presupuestario.</p>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $q() }}">Cancelar</a>
                    <a class="btn" href="{{ $q(['modal' => 'devolver']) }}">Devolver a Vicerrectoría</a>
                    <button class="btn btn-primary" type="submit">Expedir CDP</button>
                </div>
            </form>
        </div>
    @elseif ($modal === 'devolver')
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="ret-title" method="post" action="{{ route('fin.compras.devolver', $sel->code) }}">
                @csrf
                <div class="modal-head">
                    <div>
                        <h2 id="ret-title">Devolver {{ $sel->code }} a Vicerrectoría</h2>
                        <p>{{ $sel->description }} · {{ $sel->project->name }}</p>
                    </div>
                    <a class="btn btn-icon" href="{{ $q() }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <p class="alert warn" style="margin:0"><span class="alert-body">La solicitud vuelve a Vicerrectoría con tus observaciones. No se compromete presupuesto hasta que se reenvíe.</span></p>
                    <div class="field">
                        <label for="reason">Motivo *</label>
                        <select id="reason" name="reason">
                            @foreach (FinanceStore::RETURN_REASONS as $r)<option @selected(old('reason') === $r)>{{ $r }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="notes">Observaciones *</label>
                        <textarea id="notes" name="notes" required placeholder="Ej.: adjuntar dos cotizaciones adicionales o solicitar traslado presupuestal.">{{ old('notes') }}</textarea>
                        @error('notes')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <label class="check"><input type="checkbox" name="notify" value="1" checked>Notificar por correo a {{ $sel->requester->email }}</label>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $q() }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Devolver</button>
                </div>
            </form>
        </div>
    @elseif ($modal === 'pago')
        @include('fin.partials.payment-modal', [
            'item' => ['code' => $sel->code, 'title' => $sel->description, 'subtitle' => $sel->project->name, 'cdp' => $sel->cdp, 'value' => (float) $sel->value, 'supplier' => $store->supplier($sel->code)],
            'voucher' => $store->nextVoucherNumber(), 'close' => $q(), 'back' => 'compras',
        ])
    @endif
@endsection
