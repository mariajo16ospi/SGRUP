@extends('layouts.app')
@section('title', 'Mis solicitudes de compra')
@section('content')
    @php
        $sel = $selected;
        $q = fn (array $extra = []) => route('inv.compras', array_filter(['estado' => $tab === 'todas' ? null : $tab, 'ver' => $sel?->code] + $extra));
        $editing = $modal === 'editar' && $sel && $sel->status === 'requiere_ajuste';
        $form = $editing ? [
            'project_id' => $sel->project_id, 'description' => $sel->description, 'original_value' => (int) ($sel->original_value ?? $sel->value),
            'line' => $sel->budgetLine->name, 'value' => (int) $sel->value, 'justification' => '',
        ] : ['project_id' => $defaultProject, 'description' => '', 'original_value' => '', 'line' => 'Equipos', 'value' => '', 'justification' => ''];
        $lastNote = $sel ? collect($sel->changes_log)->last() : null;
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Portal del investigador</div>
            <h1>Mis solicitudes de compra</h1>
            <p class="sub">Crea solicitudes y sigue su aprobación hasta el pago</p>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('inv.compras', ['modal' => 'nueva']) }}"><x-icon name="plus" size="16" />Nueva solicitud</a>
            <a class="btn btn-icon" href="{{ route('inv.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat"><span class="stat-label">Solicitudes del mes</span><span class="stat-value">{{ $ofMonth->count() }}</span><span class="stat-foot">{{ $pendingOfMonth }} en aprobación</span></div>
        <div class="card stat"><span class="stat-label">Monto solicitado</span><span class="stat-value">{{ money_m($ofMonth->sum('value')) }}</span><span class="stat-foot">{{ month_long($month) }}</span></div>
        <div class="card stat"><span class="stat-label">Aprobado / pagado</span><span class="stat-value green">{{ money_m($approved->sum('value')) }}</span><span class="stat-foot">{{ $approved->count() }} solicitudes</span></div>
        <div class="card stat" style="{{ $returned->isNotEmpty() ? 'border-color:var(--amber-line);background:#fffaf0' : '' }}">
            <span class="stat-label">Devueltas para ajuste</span><span class="stat-value">{{ $returned->count() }}</span>
            <span class="stat-foot">@if ($returned->isNotEmpty())<a href="{{ route('inv.compras', ['ver' => $returned->first()->code]) }}">{{ $returned->first()->code }} · revisa las observaciones</a>@else Ninguna @endif</span>
        </div>
    </div>

    <div class="split">
        <section class="card">
            <h2>Solicitudes de compra</h2>
            <p class="flow-hint">Flujo: Coordinador → Vicerrectoría → Área financiera → Pago</p>
            <nav class="chips" aria-label="Filtrar por estado">
                @foreach ($tabs as $key => $t)
                    <a href="{{ route('inv.compras', array_filter(['estado' => $key === 'todas' ? null : $key])) }}" @class(['on' => $tab === $key])>{{ $t['label'] }} ({{ $t['count'] }})</a>
                @endforeach
            </nav>
            @if ($list->isEmpty())
                <p class="empty">No tienes solicitudes en este estado.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Código</th><th>Descripción</th><th class="num">Valor</th><th>Estado</th></tr></thead>
                        <tbody>
                        @foreach ($list as $p)
                            <tr @class(['selected' => $sel && $p->code === $sel->code])>
                                <td style="white-space:nowrap">
                                    <a class="title" href="{{ route('inv.compras', array_filter(['estado' => $tab === 'todas' ? null : $tab, 'ver' => $p->code])) }}">{{ $p->code }}</a>
                                    <div class="meta">{{ date_es($p->submitted_at) }}</div>
                                </td>
                                <td style="min-width:200px"><div class="title">{{ $p->description }}</div><div class="meta">{{ $p->project->name }}</div></td>
                                <td class="num strong">{{ money($p->value) }}</td>
                                <td><span class="badge {{ $p->statusTone() }}">{{ $p->status === 'requiere_ajuste' ? 'Devuelta para ajuste' : $p->statusLabel() }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($sel)
            @php
                $quoted = (float) ($sel->original_value ?? $sel->value);
                $diff = (float) $sel->value - $quoted;
                $insufficient = in_array($sel->status, ['en_vicerrectoria', 'requiere_ajuste'], true) && $line['available'] < (float) $sel->value;
                $flow = [
                    ['Investigador / Coordinador', 'Creó la solicitud · ' . $sel->requester->name . ' · ' . date_es($sel->submitted_at), $sel->status === 'requiere_ajuste' ? 'current' : 'done'],
                    ['Vicerrectoría / Subdirección', match ($sel->status) {
                        'en_vicerrectoria' => 'En revisión técnica', 'rechazada' => 'Rechazada', 'requiere_ajuste' => 'Devuelta · ' . \Illuminate\Support\Str::after($lastNote['text'] ?? 'pidió ajustes', ': '), 'retirada' => 'Retirada por el investigador', default => 'Aprobada',
                    }, match ($sel->status) { 'en_vicerrectoria' => 'current', 'rechazada', 'requiere_ajuste' => 'error', 'retirada' => 'next', default => 'done' }],
                    ['Área financiera', $sel->cdp ? "CDP {$sel->cdp} expedido" : 'Valida disponibilidad y expide CDP', $sel->cdp || $sel->status === 'pagada' ? 'done' : ($sel->status === 'en_financiera' ? 'current' : 'next')],
                    ['Pago', $sel->status === 'pagada' ? 'Pago registrado' : 'Registro del gasto y soporte', $sel->status === 'pagada' ? 'done' : 'next'],
                ];
            @endphp
            <aside class="card detail" aria-label="Detalle de la solicitud">
                <div style="display:flex;justify-content:space-between;gap:8px;align-items:center">
                    <span class="eyebrow-sm">Solicitud {{ $sel->code }}</span>
                    <span class="badge {{ $sel->statusTone() }}">{{ $sel->status === 'requiere_ajuste' ? 'Devuelta' : $sel->statusLabel() }}</span>
                </div>
                <h3>{{ $sel->description }}</h3>
                <p class="muted" style="font-size:13px">Proyecto: {{ $sel->project->name }} · Rubro: {{ $sel->budgetLine->name }}</p>

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
                    <a class="btn btn-sm" style="margin-top:8px" href="{{ route('inv.proyectos.presupuesto', [$sel->project, 'modal' => 'traslado']) }}"><x-icon name="refresh" size="14" />Solicitar traslado presupuestal</a>
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

                @if (in_array($sel->status, ['en_vicerrectoria', 'requiere_ajuste'], true))
                    <div class="detail-actions">
                        <form method="post" action="{{ route('inv.compras.retirar', $sel->code) }}" onsubmit="return confirm('¿Retirar la solicitud {{ $sel->code }}? No se podrá reenviar.')">
                            @csrf
                            <button class="btn btn-danger" type="submit">Retirar solicitud</button>
                        </form>
                        @if ($sel->status === 'requiere_ajuste')
                            <a class="btn btn-primary" href="{{ $q(['modal' => 'editar']) }}">Editar y reenviar</a>
                        @endif
                    </div>
                @endif
            </aside>
        @endif
    </div>

    @if ($modal === 'nueva' || $editing)
        @php $close = $q(); @endphp
        <div class="modal-backdrop">
            <form class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="sc-title" method="post" enctype="multipart/form-data"
                  action="{{ $editing ? route('inv.compras.reenviar', $sel->code) : route('inv.compras.store') }}" id="purchase-form">
                @csrf
                @if ($editing) @method('PUT') <input type="hidden" name="_form" value="editar"> @else <input type="hidden" name="_form" value="nueva"> @endif
                <div class="modal-head">
                    <div><h2 id="sc-title">{{ $editing ? "Editar y reenviar {$sel->code}" : 'Nueva solicitud de compra' }}</h2>
                        <p>{{ $editing ? 'Ajusta la solicitud según las observaciones de Vicerrectoría.' : 'Vicerrectoría la revisa y luego el área financiera expide el CDP.' }}</p></div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="stack" style="gap:14px">
                            <div class="field">
                                <label for="project_id">Proyecto</label>
                                <select id="project_id" name="project_id" @disabled($editing)>
                                    @foreach ($projects as $p)<option value="{{ $p->id }}" @selected((int) old('project_id', $form['project_id']) === $p->id)>{{ $p->code }} · {{ $p->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="description">Bien o servicio a adquirir *</label>
                                <input id="description" name="description" value="{{ old('description', $form['description']) }}" required>
                                @error('description')<span class="error">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="original_value">Valor cotizado inicialmente ($)</label>
                                <input id="original_value" name="original_value" type="number" min="0" value="{{ old('original_value', $form['original_value']) }}">
                                <span class="modal-note">Si el precio cambió, el sistema lo marca como sobrecosto.</span>
                            </div>
                            <div class="field">
                                <label for="justification">Justificación *</label>
                                <textarea id="justification" name="justification" required placeholder="¿Para qué producto del proyecto se necesita?">{{ old('justification', $form['justification']) }}</textarea>
                                @error('justification')<span class="error">{{ $message }}</span>@enderror
                            </div>
                            <div class="field">
                                <label for="quotes">Cotizaciones (opcional)</label>
                                <input id="quotes" name="quotes[]" type="file" multiple style="height:auto;padding:8px">
                            </div>
                        </div>
                        <div class="stack" style="gap:14px">
                            <div class="field">
                                <label for="line">Rubro</label>
                                <select id="line" name="line" @disabled($editing)></select>
                            </div>
                            <div class="field">
                                <label for="value">Valor a solicitar ($) *</label>
                                <input id="value" name="value" type="number" min="10000" value="{{ old('value', $form['value']) }}" required>
                                @error('value')<span class="error">{{ $message }}</span>@enderror
                            </div>
                            <div class="box" style="margin-top:0" aria-live="polite">
                                <h4>Validación automática de presupuesto</h4>
                                <dl class="kv">
                                    <dt>Asignado al rubro</dt><dd id="v-budget">—</dd>
                                    <dt>Ejecutado + comprometido</dt><dd id="v-used">—</dd>
                                    <dt class="strong">Disponible</dt><dd id="v-available">—</dd>
                                    <dt>Esta solicitud</dt><dd id="v-value">—</dd>
                                </dl>
                                <p id="v-ok" class="flash ok" style="margin:10px 0 0;display:none"></p>
                                <p id="v-bad" class="flash err" style="margin:10px 0 0;display:none"></p>
                                <p id="v-over" class="flash err" style="margin:10px 0 0;display:none;background:#fffaf0;color:#7a4a00;border-color:var(--amber-line)"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $close }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">{{ $editing ? 'Reenviar a Vicerrectoría' : 'Enviar a aprobación' }}</button>
                </div>
            </form>
        </div>
        <script>
            (function () {
                const lines = @json($lineData);
                const selected = @json(old('line', $form['line']));
                const fmt = n => '$' + Math.round(n).toLocaleString('es-CO');
                const $ = id => document.getElementById(id);
                function fillLines() {
                    const list = lines[$('project_id').value] || [];
                    $('line').innerHTML = list.map(l => `<option value="${l.name}" ${l.name === ($('line').dataset.v || selected) ? 'selected' : ''}>${l.name} (disp. ${fmt(l.available)})</option>`).join('');
                    update();
                }
                function update() {
                    const l = (lines[$('project_id').value] || []).find(x => x.name === $('line').value);
                    if (!l) return;
                    const v = Number($('value').value) || 0, q = Number($('original_value').value) || 0;
                    $('v-budget').textContent = fmt(l.budgeted);
                    $('v-used').textContent = fmt(l.executed + l.committed);
                    $('v-available').textContent = fmt(l.available);
                    $('v-value').textContent = fmt(v);
                    const ok = v <= l.available;
                    $('v-ok').style.display = ok && v ? 'block' : 'none';
                    $('v-ok').textContent = 'Hay presupuesto disponible. Quedará un saldo de ' + fmt(l.available - v) + ' en el rubro.';
                    $('v-bad').style.display = ok ? 'none' : 'block';
                    $('v-bad').textContent = 'Presupuesto insuficiente: faltan ' + fmt(v - l.available) + '. Necesitarás un traslado presupuestal aprobado por Vicerrectoría.';
                    $('v-over').style.display = q && v > q ? 'block' : 'none';
                    $('v-over').textContent = 'Sobrecosto de ' + fmt(v - q) + ' (+' + Math.round((v - q) / q * 100) + '%). Explica la variación en la justificación.';
                }
                $('project_id').addEventListener('change', fillLines);
                $('line').addEventListener('change', e => { $('line').dataset.v = e.target.value; update(); });
                $('value').addEventListener('input', update);
                $('original_value').addEventListener('input', update);
                fillLines();
                // Los campos deshabilitados no se envían: al reenviar se usan los del original
                document.getElementById('purchase-form').addEventListener('submit', () => { $('project_id').disabled = false; $('line').disabled = false; });
            })();
        </script>
    @endif
@endsection
