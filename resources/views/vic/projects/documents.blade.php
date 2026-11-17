@extends('layouts.app')
@section('title', 'Documentos · ' . $project->name)
@section('content')
    @php
        use App\Support\FinanceStore;
        $done = $checklist->where('done', true)->count();
    @endphp
    @include('vic.projects._header')

    <div class="cols" style="grid-template-columns:minmax(0,1fr) 340px">
        <section class="card">
            <h2>Documentos del proyecto</h2>
            <p class="hint" style="margin-bottom:12px">{{ $all->count() }} documentos · contrato, actas, pólizas, informes y soportes</p>
            <nav class="tabs" aria-label="Filtrar por tipo" style="margin-bottom:8px">
                <a href="{{ route($area . '.proyectos.documentos', $project) }}" @class(['on' => ! $cat])>Todos</a>
                @foreach (FinanceStore::DOC_CATEGORIES as $key => $label)
                    <a href="{{ route($area . '.proyectos.documentos', [$project, 'tipo' => $key]) }}" @class(['on' => $cat === $key])>{{ $label }}</a>
                @endforeach
            </nav>
            @forelse ($list as $doc)
                <div class="doc">
                    <span class="ext {{ strtolower($doc['ext']) }}" aria-hidden="true">{{ $doc['ext'] }}</span>
                    <div class="info">
                        <b><a href="{{ request()->fullUrlWithQuery(['ver' => $doc['id'], 'modal' => null]) }}">{{ $doc['name'] }}</a></b>
                        <span>{{ $doc['type'] }}</span><br>
                        <span>{{ $doc['by'] }} · {{ date_es($doc['date']) }} · {{ number_format($doc['size'], 1, ',', '.') }} MB</span>
                    </div>
                    @if ($doc['validated'])
                        <span class="badge green">Validado</span>
                    @else
                        <span class="badge amber">En revisión</span>
                        @if ($area === 'vic')
                            <form method="post" action="{{ route('vic.proyectos.documentos.validar', [$project, $doc['id']]) }}">
                                @csrf
                                <button class="btn btn-sm" type="submit">Validar<span class="sr-only"> {{ $doc['name'] }}</span></button>
                            </form>
                        @endif
                    @endif
                </div>
            @empty
                <p class="empty">No hay documentos de este tipo.</p>
            @endforelse
        </section>

        <div class="stack">
            <section class="card">
                <h2 style="margin-bottom:12px">Cargar nuevo documento</h2>
                <a class="dropzone" href="{{ route($area . '.proyectos.documentos', [$project, 'modal' => 'subir']) }}" style="text-decoration:none">
                    <x-icon name="upload" size="24" />
                    <b>Haz clic para subir archivos</b>
                    <span>PDF, DOCX, XLSX, ZIP, imágenes</span>
                    <span class="btn btn-sm">Seleccionar archivo</span>
                </a>
            </section>
            <section class="card">
                <h2>Checklist RUP · Cámara de Comercio</h2>
                <p class="hint" style="margin-bottom:12px">Documentos para certificar la experiencia institucional</p>
                <ul class="steps">
                    @foreach ($checklist as $i => $c)
                        <li><span class="step {{ $c['done'] ? 'done' : 'next' }}">@if($c['done'])<x-icon name="check" size="12" />@else{{ $i + 1 }}@endif</span>
                            <span><b style="font-weight:500">{{ $c['type'] }}</b><span class="meta">{{ $c['done'] ? 'Validado' : 'Pendiente' }}</span></span></li>
                    @endforeach
                </ul>
                <p class="strong" style="margin:14px 0 10px">{{ $done }} de {{ $checklist->count() }} completos</p>
                @if ($done === $checklist->count() && $area === 'vic')
                    <a class="btn btn-primary" style="width:100%" href="{{ route('vic.proyectos.certificacion', $project) }}">Generar certificación de experiencia</a>
                @else
                    <button class="btn" style="width:100%" type="button" disabled>Generar certificación de experiencia</button>
                    <p class="hint" style="margin-top:6px">{{ $area === 'vic' ? 'Se habilita cuando los ' . $checklist->count() . ' documentos estén validados.' : 'La genera Vicerrectoría cuando los ' . $checklist->count() . ' documentos estén validados.' }}</p>
                @endif
            </section>
        </div>
    </div>

    @if ($modal)
        @php $close = route($area . '.proyectos.documentos', $project); @endphp
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="up-title" method="post" enctype="multipart/form-data" action="{{ route($area . '.proyectos.documentos.subir', $project) }}">
                @csrf
                <input type="hidden" name="_form" value="subir">
                <div class="modal-head">
                    <div><h2 id="up-title">Subir documento</h2><p>{{ $project->code }} · {{ $project->name }}</p></div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="field">
                        <label for="type">Tipo de documento</label>
                        <select id="type" name="type">
                            @foreach (array_keys(FinanceStore::DOC_TYPES) as $type)<option @selected(old('type', 'Contrato o convenio') === $type)>{{ $type }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="file">Archivo</label>
                        <input id="file" name="file" type="file" required accept=".pdf,.docx,.xlsx,.zip,.png,.jpg,.jpeg" style="height:auto;padding:8px">
                        @error('file')<span class="error">{{ $message }}</span>@enderror
                        <span class="modal-note">Archivos de hasta 1,5 MB quedan guardados para descargar en esta demo.</span>
                    </div>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $close }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Subir</button>
                </div>
            </form>
        </div>
    @endif

    @if ($preview = $all->firstWhere('id', request('ver')))
        @include('partials.document-preview', ['doc' => $preview, 'close' => request()->fullUrlWithQuery(['ver' => null])])
    @endif
@endsection
