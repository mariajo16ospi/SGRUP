@extends('layouts.app')
@section('title', 'Avances mensuales · ' . $project->name)
@section('content')
    @php $statusBadge = ['aprobado' => ['Aprobado', 'green'], 'pendiente' => ['Por revisar', 'amber'], 'devuelto' => ['Devuelto', 'red']]; @endphp
    @include('vic.projects._header')

    <section class="card" style="margin-bottom:20px">
        <div class="card-head">
            <div>
                <h2>Calendario de reportes mensuales</h2>
                <p class="hint">Cada mes el coordinador carga el avance técnico y financiero</p>
            </div>
            @if ($pendingProgress)
                <a class="btn btn-primary" href="{{ route($area . '.proyectos.avances', [$project, 'revisar' => 1]) }}">Revisar último avance</a>
            @endif
            @if ($nextReport)
                <a class="btn btn-primary" href="{{ route('inv.proyectos.avances', [$project, 'cargar' => 1]) }}"><x-icon name="upload" size="16" />Cargar avance</a>
            @endif
        </div>
        <div class="report-calendar">
            @foreach ($calendar as $m)
                <div class="month {{ $m['state'][1] }}"><b>{{ month_short($m['month']) }}</b><span class="badge {{ $m['state'][1] }}">{{ $m['state'][0] }}</span></div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2 style="margin-bottom:12px">Historial de avances</h2>
        @if ($reports->isEmpty())
            <p class="empty">Todavía no se han cargado avances.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Periodo</th><th>Cargado por</th><th>Fecha</th><th class="num">Gasto del mes</th><th>Observaciones</th><th>Estado</th></tr></thead>
                    <tbody>
                    @foreach ($reports as $r)
                        <tr>
                            <td class="title" style="white-space:nowrap">{{ month_long($r['period']) }}</td>
                            <td style="white-space:nowrap"><span class="user-cell"><span class="avatar" aria-hidden="true">{{ $r['by']->initials() }}</span>{{ $r['by']->name }}</span></td>
                            <td style="white-space:nowrap">{{ date_es($r['date']) }}</td>
                            <td class="num strong">{{ money($r['amount']) }}</td>
                            <td class="muted" style="min-width:220px">{{ $r['notes'] }}</td>
                            <td><span class="badge {{ $statusBadge[$r['status']][1] }}">{{ $statusBadge[$r['status']][0] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($review)
        @php
            $close = route($area . '.proyectos.avances', $project);
            $delivered = $deliveredInMonth->where('status', 'entregado')->count();
            $installment = $project->code === 'CTO-045-2026';
        @endphp
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="rv-title" method="post" action="{{ route($area . '.proyectos.avances.decidir', [$project, $review->id]) }}">
                @csrf
                <div class="modal-head">
                    <div>
                        <h2 id="rv-title">Revisar avance · {{ month_long($review->period) }}</h2>
                        <p>{{ $project->name }} · cargado por {{ $review->submitter->name }} el {{ date_es($review->submitted_at) }}</p>
                    </div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="box" style="margin-top:0">
                        <dl class="kv">
                            <dt>Avance técnico reportado</dt><dd>{{ $review->technical_progress }} % (+{{ $review->technical_delta }} pts)</dd>
                            <dt>Gasto del mes</dt><dd>{{ money($review->amount) }}</dd>
                            <dt>Ejecución presupuestal acumulada</dt><dd>{{ $project->financialProgress() }} %</dd>
                            <dt>Soportes adjuntos</dt><dd>{{ $review->supports_count }} archivos</dd>
                            <dt>Productos con entrega en el mes</dt><dd>{{ $delivered }} de {{ $deliveredInMonth->count() }}</dd>
                        </dl>
                    </div>
                    <fieldset style="border:0;padding:0;margin:0">
                        <legend class="strong" style="margin-bottom:6px">Verificación</legend>
                        <label class="check"><input type="checkbox" name="checks[]" value="cronograma">Los productos reportados coinciden con el cronograma</label>
                        <label class="check"><input type="checkbox" name="checks[]" value="soportes">Los soportes de gasto están completos</label>
                        <label class="check"><input type="checkbox" name="checks[]" value="evidencias">El avance técnico está justificado con evidencias</label>
                    </fieldset>
                    @if ($installment)
                        <p class="alert info" style="margin:0"><span class="alert-body">Al aprobar se habilita el pago de la cuota 3 del contrato ($250.000.000) y se notifica al área financiera.</span></p>
                    @endif
                    <div class="field">
                        <label for="comment">Concepto de Vicerrectoría</label>
                        <textarea id="comment" name="comment" placeholder="Escribe un comentario (obligatorio si devuelves el avance)">{{ old('comment') }}</textarea>
                        @error('comment')<span class="error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $close }}">Cancelar</a>
                    <button class="btn" type="submit" name="decision" value="devolver">Devolver con observaciones</button>
                    <button class="btn btn-primary" type="submit" name="decision" value="aprobar">Aprobar avance</button>
                </div>
            </form>
        </div>
    @endif
    @if ($loading)
        @php $close = route('inv.proyectos.avances', $project); @endphp
        <div class="modal-backdrop">
            <form class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="ld-title" method="post" enctype="multipart/form-data" action="{{ route('inv.proyectos.avances.store', $project) }}">
                @csrf
                <input type="hidden" name="_form" value="avance">
                <input type="hidden" name="period" value="{{ $nextReport['period']->toDateString() }}">
                <div class="modal-head">
                    <div><h2 id="ld-title">Cargar avance mensual</h2><p>{{ $project->code }} · {{ $project->name }} · periodo reportado: <b>{{ month_long($nextReport['period']) }}</b> · vence el {{ date_es($nextReport['due']) }}</p></div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <h3 class="step-title">1. Avance técnico · cantidad acumulada lograda por producto</h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Producto</th><th>Entrega</th><th class="num">Actual</th><th>Nuevo acumulado</th></tr></thead>
                            <tbody>
                            @foreach ($products as $p)
                                <tr>
                                    <td><div class="title">{{ $p['name'] }}</div><div class="meta">Meta: {{ $p['target'] }} {{ $p['unit'] }}</div></td>
                                    <td style="white-space:nowrap">{{ date_es($p['due']) }}</td>
                                    <td class="num">{{ $p['done'] }}</td>
                                    <td><label class="sr-only" for="pr{{ $p['id'] }}">Nuevo acumulado de {{ $p['name'] }}</label>
                                        <input class="input-sm" id="pr{{ $p['id'] }}" name="products[{{ $p['id'] }}]" type="number" min="0" value="{{ old('products.' . $p['id'], $p['done']) }}"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h3 class="step-title">2. Avance presupuestario · gastos ejecutados en el periodo</h3>
                    <div id="expenses" class="stack" style="gap:8px">
                        @foreach (range(0, 2) as $i)
                            <div class="expense-row">
                                <label class="sr-only" for="el{{ $i }}">Rubro</label>
                                <select id="el{{ $i }}" name="expenses[{{ $i }}][line]">@foreach ($project->budgetLines->keys() as $l)<option @selected(old("expenses.$i.line") === $l)>{{ $l }}</option>@endforeach</select>
                                <label class="sr-only" for="ec{{ $i }}">Concepto</label>
                                <input id="ec{{ $i }}" name="expenses[{{ $i }}][concept]" value="{{ old("expenses.$i.concept") }}" placeholder="Concepto (ej.: combustible y viáticos visitas a fincas)">
                                <label class="sr-only" for="ev{{ $i }}">Valor</label>
                                <input id="ev{{ $i }}" name="expenses[{{ $i }}][value]" type="number" min="0" value="{{ old("expenses.$i.value") }}" placeholder="Valor $">
                            </div>
                        @endforeach
                    </div>
                    <button class="btn btn-sm" type="button" onclick="addExpense()" style="align-self:flex-start"><x-icon name="plus" size="14" />Agregar gasto</button>

                    <h3 class="step-title">3. Observaciones y soportes</h3>
                    <div class="field">
                        <label for="notes">Descripción de actividades realizadas *</label>
                        <textarea id="notes" name="notes" required placeholder="Ej.: se realizaron 3 talleres con 45 productores en Pitalito…">{{ old('notes') }}</textarea>
                        @error('notes')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="files">Soportes (informe, listas de asistencia, fotos, facturas)</label>
                        <input id="files" name="files[]" type="file" multiple style="height:auto;padding:8px">
                    </div>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $close }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Enviar avance</button>
                </div>
            </form>
        </div>
        <script>
            function addExpense() {
                const box = document.getElementById('expenses');
                const row = box.lastElementChild.cloneNode(true);
                const i = box.children.length;
                row.querySelectorAll('[name]').forEach(el => {
                    el.name = el.name.replace(/\[\d+\]/, '[' + i + ']');
                    el.id = el.id.replace(/\d+$/, i);
                    if (el.tagName !== 'SELECT') el.value = '';
                });
                row.querySelectorAll('label').forEach(l => { l.htmlFor = l.htmlFor.replace(/\d+$/, i); });
                box.appendChild(row);
                row.querySelector('input').focus();
            }
        </script>
    @endif
@endsection
