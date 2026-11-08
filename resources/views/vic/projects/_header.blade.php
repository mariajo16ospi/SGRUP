{{-- Encabezado y pestañas comunes del proyecto (VIC 04–10). Requiere $project, $tab, $area, $pendingProgress y $nextReport. --}}
@php use App\Http\Controllers\Vic\ProjectController; @endphp
<div class="page-head" style="border-bottom:1px solid var(--line);padding-bottom:16px">
    <div>
        <div class="crumb">{{ $area === 'inv' ? 'FET · Portal del investigador' : 'FET · Vicerrectoría de Investigación y Extensión' }} <x-icon name="chevron" size="12" /> <a href="{{ route($area === 'vic' ? 'vic.proyectos.index' : 'inv.home') }}">{{ $area === 'inv' ? 'Mis proyectos' : 'Proyectos' }}</a></div>
        <h1>{{ $project->name }}</h1>
        <p class="sub">{{ $project->contractLabel() }} {{ $project->code }} · {{ $project->entity }} · Coord.: {{ $project->coordinator->name }} · {{ date_es($project->start_date) }} – {{ date_es($project->end_date) }}</p>
        <div class="actions" style="margin-top:14px">
            <button class="btn" type="button" onclick="window.print()"><x-icon name="printer" size="16" />Informe PDF</button>
            @if ($area === 'vic')
                <button class="btn" type="button" disabled title="Disponible en la siguiente fase"><x-icon name="edit" size="16" />Editar</button>
            @endif
            @if ($nextReport)
                <a class="btn btn-primary" href="{{ route('inv.proyectos.avances', [$project, 'cargar' => 1]) }}"><x-icon name="upload" size="16" />Cargar avance mensual</a>
            @endif
            @if ($pendingProgress)
                <a class="btn btn-primary" href="{{ route($area . '.proyectos.avances', [$project, 'revisar' => 1]) }}"><x-icon name="upload" size="16" />Revisar avance</a>
            @endif
        </div>
    </div>
    <a class="btn btn-icon" href="{{ route($area . '.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
</div>

<nav class="tabs" aria-label="Secciones del proyecto" style="margin-bottom:18px">
    @foreach (ProjectController::TABS as $key => $label)
        <a href="{{ route($area . '.proyectos.' . $key, $project) }}" @class(['on' => $tab === $key]) @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
