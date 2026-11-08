@extends('layouts.app')
@section('title', $project->name)
@section('content')
    @php
        $deviation = $executedToDate - $plannedToDate;
    @endphp
    @include('vic.projects._header')

    <section style="margin-bottom:16px">
        <div class="card-head" style="align-items:center">
            <h2 style="font-size:16px">Ruta de ejecución</h2>
            @if ($project->status === 'activo' && $area === 'vic')
                <div class="actions">
                    @if ($next = $project->nextPhase())
                        <form method="post" action="{{ route($area . '.proyectos.fase', $project) }}" onsubmit="return confirm('¿Pasar el proyecto a la fase de {{ \App\Models\Project::PHASES[$next] }}?')">
                            @csrf
                            <button class="btn btn-sm" type="submit"><x-icon name="forward" size="14" />Pasar a {{ \App\Models\Project::PHASES[$next] }}</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route($area . '.proyectos.liquidar', $project) }}" onsubmit="return confirm('¿Liquidar el proyecto? Esta acción lo cierra definitivamente.')">
                        @csrf
                        <button class="btn btn-sm" type="submit"><x-icon name="flag" size="14" />Liquidar proyecto</button>
                    </form>
                </div>
            @elseif ($project->status !== 'activo')
                <span class="badge gray">{{ $project->statusLabel() }}</span>
            @endif
        </div>
        <div class="route">
            @foreach ($project->phaseRoute() as $ph)
                <div class="phase {{ $ph['state'] }}">
                    <small>Fase {{ $ph['number'] }} · {{ $ph['label'] }}</small>
                    <b>
                        @if ($ph['state'] === 'done') Completada <x-icon name="check" size="14" style="color:var(--green-700);vertical-align:-2px" />
                        @elseif ($ph['state'] === 'current') En curso
                        @else Por iniciar @endif
                    </b>
                    @if ($ph['progress'] !== null)
                        <div class="bar" style="margin:6px 0"><i style="width:{{ $ph['progress'] }}%"></i></div>
                    @endif
                    <span>{{ date_es($ph['from']) }} – {{ date_es($ph['to']) }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="target" size="16" /></span>Avance técnico</span>
            <span class="stat-value">{{ $project->technical_progress }}%</span>
            <span class="stat-foot">Tiempo transcurrido: {{ $project->timeElapsedPercent() }}%</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="wallet" size="16" /></span>Presupuesto</span>
            <span class="stat-value">{{ money_m($project->value) }}</span>
            <span class="stat-foot">Valor del {{ strtolower($project->contractLabel()) }}</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon red"><x-icon name="trend" size="16" /></span>Ejecutado</span>
            <span class="stat-value">{{ money_m($project->executed_amount) }}</span>
            <span class="stat-foot">{{ $project->financialProgress() }}% · planeado a la fecha {{ $plannedPct }}%</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon amber"><x-icon name="hourglass" size="16" /></span>Tiempo restante</span>
            <span class="stat-value">{{ $project->daysRemaining() }} días</span>
            <span class="stat-foot">Finaliza {{ date_es($project->end_date) }}</span>
        </div>
    </div>

    <div class="cols">
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Curva S · Ejecución presupuestaria</h2>
                    <p class="hint">Acumulado proyectado vs. ejecutado real (pasa el cursor por los puntos)</p>
                </div>
                <div class="legend"><span><i class="dash"></i>Proyectado</span><span><i></i>Ejecutado</span><span><i class="today"></i>Hoy</span></div>
            </div>
            <x-s-curve :curve="$curve" label="Curva S del proyecto" />
            <div class="mini-stats">
                <div><span>Planeado a la fecha</span><b>{{ money($plannedToDate) }}</b></div>
                <div><span>Ejecutado real</span><b>{{ money($executedToDate) }}</b></div>
                <div><span>Desviación</span><b @class(['text-red' => $deviation < 0])>{{ money($deviation) }}</b></div>
            </div>
        </section>

        <div class="stack">
            <section class="card">
                <h2 style="margin-bottom:12px">Alertas del proyecto</h2>
                <div class="stack" style="gap:8px">
                    @forelse ($alerts as $a)
                        <x-alert-item :alert="$a" />
                    @empty
                        <p class="muted">Sin alertas activas.</p>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <h2 style="margin-bottom:12px">Ficha del proyecto</h2>
                <dl class="kv">
                    <dt>Tipo</dt><dd>{{ $project->typeLabel() }}</dd>
                    <dt>Modalidad</dt><dd>{{ $project->modalityLabel() }}</dd>
                    <dt>Entidad</dt><dd>{{ $project->entity }}</dd>
                    <dt>Código</dt><dd>{{ $project->code }}</dd>
                    <dt>Coordinador</dt><dd>{{ $project->coordinator->name }}</dd>
                    <dt>Estado</dt><dd><span class="badge {{ $project->statusTone() }}">{{ $project->statusLabel() }}</span></dd>
                </dl>
                @if ($project->general_objective)
                    <h3 style="font-size:13px;color:var(--green-700);margin:16px 0 4px;border-top:1px solid var(--line-soft);padding-top:12px">Objetivo general</h3>
                    <p>{{ $project->general_objective }}</p>
                @endif
            </section>
        </div>
    </div>
@endsection
