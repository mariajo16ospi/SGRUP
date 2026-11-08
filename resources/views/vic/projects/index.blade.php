@extends('layouts.app')
@section('title', 'Proyectos y contratos')
@section('content')
    <div class="page-head">
        <div>
            <div class="crumb">FET · Vicerrectoría de Investigación y Extensión</div>
            <h1>Proyectos y contratos</h1>
            <p class="sub">Proyectos de investigación y extensión, convenios, contratos y alianzas de la institución</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('vic.proyectos.export', ['filtro' => $filter, 'q' => $q]) }}"><x-icon name="download" size="16" />Exportar Excel</a>
            <button class="btn btn-primary" type="button" disabled title="Disponible en la siguiente fase"><x-icon name="plus" size="16" />Nuevo proyecto</button>
            <a class="btn btn-icon" href="{{ route('vic.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <div class="toolbar">
        <form class="search" method="get" action="{{ route('vic.proyectos.index') }}" role="search">
            <x-icon name="search" size="16" />
            <label for="q" class="sr-only">Buscar proyectos</label>
            <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre, código o entidad…">
            <input type="hidden" name="filtro" value="{{ $filter }}">
        </form>
        <nav class="tabs" aria-label="Filtrar proyectos">
            @foreach (\App\Http\Controllers\Vic\ProjectController::FILTERS as $key => $label)
                <a href="{{ route('vic.proyectos.index', array_filter(['filtro' => $key === 'todos' ? null : $key, 'q' => $q])) }}" @class(['on' => $filter === $key])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if ($projects->isEmpty())
        <div class="card empty">No se encontraron proyectos{{ $q ? " para «{$q}»" : '' }}.</div>
    @else
        <div class="grid-3">
            @foreach ($projects as $p)
                @php $fin = $p->financialProgress(); @endphp
                <article class="card project-card">
                    <div class="tags">
                        <span class="badge {{ $p->type === 'extension' ? 'green' : 'yellow' }}">{{ $p->typeLabel() }}</span>
                        <span class="badge gray">{{ $p->modalityLabel() }}</span>
                        <span class="badge {{ $p->statusTone() }}">{{ $p->statusLabel() }}</span>
                    </div>
                    <div>
                        <h3>{{ $p->name }}</h3>
                        <div class="code">{{ $p->code }} · {{ $p->entity }}</div>
                    </div>
                    <dl>
                        <dt>Coordinador</dt><dd>{{ $p->coordinator->name }}</dd>
                        <dt>Vigencia</dt><dd>{{ date_es($p->start_date) }} – {{ date_es($p->end_date) }}</dd>
                        <dt>Valor</dt><dd>{{ money($p->value) }}</dd>
                    </dl>
                    <div class="bars">
                        <div><span>Técnico {{ $p->technical_progress }}%</span><div class="bar"><i style="width:{{ $p->technical_progress }}%"></i></div></div>
                        <div><span>Financiero {{ $fin }}%</span><div class="bar"><i class="{{ $p->health === 'sobrecosto' && $p->status === 'activo' ? 'red' : 'dark' }}" style="width:{{ min(100, $fin) }}%"></i></div></div>
                    </div>
                    <footer>
                        <span>Fase: <b>{{ $p->phaseLabel() }}</b></span>
                        <a class="strong" href="{{ route('vic.proyectos.show', $p) }}">Abrir<span class="sr-only"> {{ $p->name }}</span> →</a>
                    </footer>
                </article>
            @endforeach
        </div>
    @endif
@endsection
