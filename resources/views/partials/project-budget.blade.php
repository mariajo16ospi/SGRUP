{{-- Presupuesto por rubros de un proyecto (FIN 04 y VIC 05).
     Requiere $project, $t, $movements, $transfers, $modal, $openTransfer, $closeUrl, $movementsUrl, $transferAction, $transferButton y $transferNote. --}}
@php $lines = $t['lines']; @endphp
    <h2 style="font-size:16px;margin-bottom:12px">Presupuesto</h2>
    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label">Asignado</span>
            <span class="stat-value">{{ money_m($t['value']) }}</span>
            <span class="stat-foot">{{ $lines->count() }} rubros</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Ejecutado</span>
            <span class="stat-value">{{ money_m($t['executed']) }}</span>
            <span class="stat-foot">{{ $t['executed_pct'] }} % del total</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Comprometido</span>
            <span class="stat-value">{{ money_m($t['committed']) }}</span>
            <span class="stat-foot">Compras con CDP sin pagar</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Disponible</span>
            <span class="stat-value green">{{ money_m($t['available']) }}</span>
            <span class="stat-foot">Saldo libre para nuevas compras</span>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px">
        <div class="card-head">
            <div>
                <h2>Presupuesto por rubros</h2>
                <p class="hint">Control presupuestario automático: asignado, ejecutado, comprometido y disponible</p>
            </div>
            @if ($project->status === 'activo')
                <a class="btn" href="{{ $openTransfer }}"><x-icon name="refresh" size="16" />Traslado presupuestal</a>
            @endif
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Rubro</th><th class="num">Asignado</th><th class="num">Ejecutado</th><th class="num">Comprometido</th><th class="num">Disponible</th><th>Uso</th></tr></thead>
                <tbody>
                @foreach ($lines as $l)
                    <tr>
                        <td class="title">{{ $l['name'] }}</td>
                        <td class="num">{{ money($l['budgeted']) }}</td>
                        <td class="num">{{ money($l['executed']) }}</td>
                        <td class="num">{{ money($l['committed']) }}</td>
                        <td class="num strong @if($l['available'] < 0) text-red @endif">{{ money($l['available']) }}</td>
                        <td style="min-width:110px"><div class="strong" style="font-size:12px">{{ $l['use'] }}%</div><div class="bar"><i class="{{ $l['use'] > 100 ? 'red' : '' }}" style="width:{{ min(100, $l['use']) }}%"></i></div></td>
                    </tr>
                @endforeach
                <tr>
                    <td class="strong">TOTAL</td>
                    <td class="num strong">{{ money($t['value']) }}</td>
                    <td class="num strong">{{ money($t['executed']) }}</td>
                    <td class="num strong">{{ money($t['committed']) }}</td>
                    <td class="num strong">{{ money($t['available']) }}</td>
                    <td class="strong">{{ $t['value'] > 0 ? round(($t['executed'] + $t['committed']) / $t['value'] * 100) : 0 }}%</td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="cols" style="grid-template-columns:minmax(0,1fr) 340px">
        <section class="card">
            <div class="card-head" style="align-items:center">
                <h2>Movimientos de gasto</h2>
                <a class="btn btn-sm" href="{{ $movementsUrl }}"><x-icon name="download" size="14" />Exportar</a>
            </div>
            @if ($movements->isEmpty())
                <p class="empty">Sin movimientos registrados.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Mes</th><th>Rubro</th><th>Descripción</th><th>Origen</th><th class="num">Valor</th></tr></thead>
                        <tbody>
                        @foreach ($movements->take(14) as $m)
                            <tr>
                                <td style="white-space:nowrap">{{ month_short($m['date']) }}</td>
                                <td>{{ $m['line'] }}</td>
                                <td>{{ $m['description'] }}</td>
                                <td class="muted">{{ $m['origin'] }}</td>
                                <td class="num strong">{{ money($m['value']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($movements->count() > 14)<p class="hint" style="margin-top:8px">Mostrando 14 de {{ $movements->count() }} movimientos · exporta para ver todos.</p>@endif
            @endif
        </section>
        <section class="card">
            <h2 style="margin-bottom:12px">Traslados presupuestales</h2>
            @forelse ($transfers as $tr)
                <div class="box" style="margin-top:0;margin-bottom:8px">
                    <div style="display:flex;justify-content:space-between;gap:8px"><b>{{ $tr['code'] }}</b><span class="badge {{ ['aprobado' => 'green', 'rechazado' => 'red'][$tr['status']] ?? 'amber' }}">{{ ['aprobado' => 'Aprobado', 'rechazado' => 'Rechazado'][$tr['status']] ?? 'Pendiente de Vicerrectoría' }}</span></div>
                    <div style="margin-top:6px">{{ $tr['from'] }} → {{ $tr['to'] }} · <b>{{ money($tr['amount']) }}</b></div>
                    <div class="meta">{{ $tr['by'] }} · {{ date_es($tr['date']) }}@if ($tr['reason']) · {{ $tr['reason'] }}@endif</div>
                </div>
            @empty
                <p class="muted">No se han realizado traslados.</p>
            @endforelse
        </section>
    </div>

    @if ($modal === 'traslado')
        @php $close = $closeUrl; @endphp
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="tr-title" method="post" action="{{ $transferAction }}">
                @csrf
                <div class="modal-head">
                    <div>
                        <h2 id="tr-title">Traslado presupuestal</h2>
                        <p>Mueve saldo disponible entre rubros del proyecto {{ $project->name }}. {{ $transferNote }}</p>
                    </div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="field">
                            <label for="from">Rubro de origen</label>
                            <select id="from" name="from">
                                @foreach ($lines as $l)<option value="{{ $l['name'] }}" @selected(old('from') === $l['name'])>{{ $l['name'] }} (disp. {{ money_m($l['available']) }})</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="to">Rubro de destino</label>
                            <select id="to" name="to">
                                @foreach ($lines as $l)<option value="{{ $l['name'] }}" @selected(old('to', 'Equipos') === $l['name'])>{{ $l['name'] }}</option>@endforeach
                            </select>
                            @error('to')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="field">
                        <label for="amount">Valor a trasladar ($)</label>
                        <input id="amount" name="amount" type="number" min="100000" step="1" value="{{ old('amount', 5000000) }}" required>
                        @error('amount')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="reason">Motivo / justificación</label>
                        <textarea id="reason" name="reason" placeholder="Ej.: Incremento del precio del dron por tasa de cambio">{{ old('reason') }}</textarea>
                    </div>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $close }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">{{ $transferButton }}</button>
                </div>
            </form>
        </div>
    @endif
