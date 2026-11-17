@extends('layouts.app')
@section('title', 'Cronograma de proyectos')
@section('content')
    <div class="page-head">
        <div>
            <div class="crumb">{{ $area === 'inv' ? 'FET · Portal del investigador' : 'FET · Vicerrectoría de Investigación y Extensión' }}</div>
            <h1>Cronograma de proyectos</h1>
            <p class="sub">Fases de preinversión, inversión y operación con hitos de entrega de productos</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route($area . '.cronograma', $withClosed ? [] : ['liquidados' => 1]) }}" role="switch" aria-checked="{{ $withClosed ? 'true' : 'false' }}">
                <span class="switch" aria-hidden="true" aria-checked="{{ $withClosed ? 'true' : 'false' }}" style="width:34px;height:20px"></span>Incluir liquidados
            </a>
            <x-notifications />
        </div>
    </div>

    <section class="card">
        <div class="legend" style="margin-bottom:14px">
            <span><i class="sw preinversion"></i>Preinversión</span>
            <span><i class="sw inversion"></i>Inversión</span>
            <span><i class="sw operacion"></i>Operación</span>
            <span><i class="ms pending"></i>Entrega pendiente</span>
            <span><i class="ms done"></i>Entrega cumplida</span>
            <span><i class="ms late"></i>Entrega vencida</span>
            <span><i class="today"></i>Hoy</span>
        </div>
        <div class="table-wrap">
            <div class="gantt" style="--months: {{ $months->count() }}">
                <div class="gantt-row gantt-head">
                    <div class="gantt-label">Proyecto</div>
                    <div class="gantt-track">
                        @foreach ($months as $m)<span>{{ month_short($m) }}</span>@endforeach
                    </div>
                </div>
                @foreach ($rows as $r)
                    <div class="gantt-row">
                        <div class="gantt-label">
                            <a class="row-link title" href="{{ route($area . '.proyectos.objetivos', $r['project']) }}">{{ $r['project']->name }}</a>
                            <span class="meta">{{ $r['project']->code }} · {{ $r['project']->technical_progress }}% técnico</span>
                        </div>
                        <div class="gantt-track">
                            @foreach ($r['phases'] as $ph)
                                <span class="phase-bar {{ $ph['key'] }}" style="left:{{ $ph['left'] }}%;width:{{ $ph['width'] }}%" title="{{ $ph['title'] }}"><span class="sr-only">{{ $ph['title'] }}</span></span>
                            @endforeach
                            @foreach ($r['milestones'] as $m)
                                <span class="milestone {{ $m['state'] }}" style="left:{{ $m['left'] }}%" title="{{ $m['title'] }}"><span class="sr-only">{{ $m['title'] }}</span></span>
                            @endforeach
                            <span class="today-line" style="left:{{ $today }}%" aria-hidden="true"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <p class="hint" style="margin-top:10px">Pasa el cursor sobre una fase o un hito para ver sus fechas.</p>
    </section>
@endsection
