{{-- Encabezado y pestañas comunes del proyecto (VIC 04–10). Requiere $project, $tab, $area, $pendingProgress y $nextReport. --}}
@php use App\Http\Controllers\Vic\ProjectController; @endphp
<div class="page-head" style="border-bottom:1px solid var(--line);padding-bottom:16px">
    <div>
        <div class="crumb">{{ $area === 'inv' ? 'FET · Portal del investigador' : 'FET · Vicerrectoría de Investigación y Extensión' }} <x-icon name="chevron" size="12" /> <a href="{{ route($area === 'vic' ? 'vic.proyectos.index' : 'inv.home') }}">{{ $area === 'inv' ? 'Mis proyectos' : 'Proyectos' }}</a></div>
        <h1>{{ $project->name }}</h1>
        <p class="sub">{{ $project->contractLabel() }} {{ $project->code }} · {{ $project->entity }} · Coord.: {{ $project->coordinator->name }} · {{ date_es($project->start_date) }} – {{ date_es($project->end_date) }}</p>
        <div class="actions" style="margin-top:14px">
            <button class="btn" type="button" onclick="window.print()"><x-icon name="printer" size="16" />Informe PDF</button>
            @if ($area === 'vic' && $project->status === 'activo')
                <a class="btn" href="{{ request()->fullUrlWithQuery(['modal' => 'editar']) }}"><x-icon name="edit" size="16" />Editar</a>
            @endif
            @if ($nextReport)
                <a class="btn btn-primary" href="{{ route('inv.proyectos.avances', [$project, 'cargar' => 1]) }}"><x-icon name="upload" size="16" />Cargar avance mensual</a>
            @endif
            @if ($pendingProgress)
                <a class="btn btn-primary" href="{{ route($area . '.proyectos.avances', [$project, 'revisar' => 1]) }}"><x-icon name="upload" size="16" />Revisar avance</a>
            @endif
        </div>
    </div>
    <x-notifications />
</div>

<nav class="tabs" aria-label="Secciones del proyecto" style="margin-bottom:18px">
    @foreach (ProjectController::TABS as $key => $label)
        <a href="{{ route($area . '.proyectos.' . $key, $project) }}" @class(['on' => $tab === $key]) @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

@if ($area === 'vic' && $project->status === 'activo' && (request('modal') === 'editar' || old('_form') === 'editar-proyecto'))
    @php
        $close = route('vic.proyectos.' . $tab, $project);
        $projectStore = app(\App\Support\ProjectStore::class);
        $coordinators = $projectStore->coordinators();
    @endphp
    <div class="modal-backdrop">
        <form class="modal" role="dialog" aria-modal="true" aria-labelledby="ed-title" method="post" action="{{ route('vic.proyectos.update', $project) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="editar-proyecto">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="modal-head">
                <div><h2 id="ed-title">Editar proyecto</h2><p>{{ $project->code }} · {{ $project->name }}</p></div>
                <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
            </div>
            <div class="modal-body">
                <div class="field">
                    <label for="ed-name">Nombre del proyecto *</label>
                    <input id="ed-name" name="name" value="{{ old('name', $project->name) }}" required autofocus>
                    @error('name')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="row">
                    <div class="field">
                        <label for="ed-code">Código / N.º contrato *</label>
                        <input id="ed-code" name="code" value="{{ old('code', $project->code) }}" required>
                        @error('code')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="ed-coord">Coordinador *</label>
                        <select id="ed-coord" name="coordinator_id">
                            @foreach ($coordinators as $u)<option value="{{ $u->id }}" @selected((int) old('coordinator_id', $project->coordinator_id) === $u->id)>{{ $u->name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="field">
                        <label for="ed-type">Tipo *</label>
                        <select id="ed-type" name="type">
                            @foreach (\App\Models\Project::TYPES as $k => $label)<option value="{{ $k }}" @selected(old('type', $project->type) === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="ed-mod">Modalidad *</label>
                        <select id="ed-mod" name="modality">
                            @foreach (\App\Models\Project::MODALITIES as $k => $label)<option value="{{ $k }}" @selected(old('modality', $project->modality) === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
                @include('vic.projects._entity-picker', ['entities' => $projectStore->entities($project), 'selected' => old('entity_id', $project->entity_id), 'id' => 'ed-entity'])
                <div class="field">
                    <label for="ed-obj">Objetivo general *</label>
                    <textarea id="ed-obj" name="general_objective" required>{{ old('general_objective', $project->general_objective) }}</textarea>
                    @error('general_objective')<span class="error">{{ $message }}</span>@enderror
                </div>
                <p class="modal-note">Las fechas, el valor y los rubros se ajustan con prórrogas y traslados presupuestales; los cambios quedan en la trazabilidad.</p>
            </div>
            <div class="modal-foot">
                <a class="btn" href="{{ $close }}">Cancelar</a>
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
            </div>
        </form>
    </div>
@endif
