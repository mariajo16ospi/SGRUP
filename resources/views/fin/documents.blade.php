@extends('layouts.app')
@section('title', 'Soportes y pagos')
@section('content')
    @php
        use App\Support\FinanceStore;
        $area ??= 'fin';
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">{{ $area === 'inv' ? 'FET · Portal del investigador' : 'FET · Dirección Financiera' }}</div>
            <h1>Gestión documental</h1>
            <p class="sub">Repositorio centralizado por proyecto · soporte de experiencia para el RUP (Cámara de Comercio)</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route($area . '.documentos.rup') }}"><x-icon name="download" size="16" />Paquete Cámara de Comercio</a>
            <a class="btn btn-primary" href="{{ route($area . '.documentos', ['modal' => 'subir']) }}"><x-icon name="upload" size="16" />Subir documento</a>
            <a class="btn btn-icon" href="{{ route($area . '.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="folder" size="16" /></span>Documentos</span>
            <span class="stat-value">{{ $all->count() }}</span>
            <span class="stat-foot">En todos los proyectos</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="checkc" size="16" /></span>Validados</span>
            <span class="stat-value green">{{ $all->where('validated', true)->count() }}</span>
            <span class="stat-foot">Revisados por Vicerrectoría o Financiera</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon amber"><x-icon name="hourglass" size="16" /></span>En revisión</span>
            <span class="stat-value">{{ $all->where('validated', false)->count() }}</span>
            <span class="stat-foot">Pendientes de validar</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon gray"><x-icon name="flag" size="16" /></span>Actas de liquidación</span>
            <span class="stat-value">{{ $all->where('type', 'Acta de liquidación')->count() }}</span>
            <span class="stat-foot">Proyectos cerrados</span>
        </div>
    </div>

    <div class="cols" style="grid-template-columns:minmax(0,1fr) 340px">
        <section class="card">
            <h2>Documentos</h2>
            <p class="hint" style="margin-bottom:12px">{{ $all->count() }} documentos · contrato, actas, pólizas, informes y soportes</p>
            <div class="toolbar">
                <nav class="tabs" aria-label="Filtrar por tipo">
                    <a href="{{ route($area . '.documentos', array_filter(['q' => $q])) }}" @class(['on' => ! $cat])>Todos</a>
                    @foreach (FinanceStore::DOC_CATEGORIES as $key => $label)
                        <a href="{{ route($area . '.documentos', array_filter(['tipo' => $key, 'q' => $q])) }}" @class(['on' => $cat === $key])>{{ $label }}</a>
                    @endforeach
                </nav>
                <form class="search" method="get" action="{{ route($area . '.documentos') }}" role="search">
                    <x-icon name="search" size="16" />
                    <label for="q" class="sr-only">Buscar documentos</label>
                    <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre, tipo o proyecto…">
                    @if ($cat)<input type="hidden" name="tipo" value="{{ $cat }}">@endif
                </form>
            </div>
            @forelse ($list as $doc)
                <div class="doc">
                    <span class="ext {{ strtolower($doc['ext']) }}" aria-hidden="true">{{ $doc['ext'] }}</span>
                    <div class="info">
                        @if ($doc['path'])
                            <b><a href="{{ route('documentos.descargar', $doc['id']) }}">{{ $doc['name'] }}</a></b>
                        @else
                            <b>{{ $doc['name'] }}</b>
                        @endif
                        <span>{{ $doc['type'] }} · {{ $doc['project']->name }}</span><br>
                        <span>{{ $doc['by'] }} · {{ date_es($doc['date']) }} · {{ number_format($doc['size'], 1, ',', '.') }} MB</span>
                    </div>
                    @if ($doc['validated'])
                        <span class="badge green">Validado</span>
                    @else
                        <span class="badge amber">En revisión</span>
                        @if ($area === 'fin')
                            <form method="post" action="{{ route('fin.documentos.validar', $doc['id']) }}">
                                @csrf
                                <button class="btn btn-sm" type="submit">Validar<span class="sr-only"> {{ $doc['name'] }}</span></button>
                            </form>
                        @endif
                    @endif
                </div>
            @empty
                <p class="empty">No hay documentos con estos filtros.</p>
            @endforelse
            <p class="hint" style="margin-top:10px">
                Mostrando {{ $list->count() }} de {{ $filtered }} documentos
                @if ($list->count() < $filtered) · <a href="{{ route($area . '.documentos', array_filter(['tipo' => $cat, 'q' => $q, 'todos' => 1])) }}">ver todos</a>@endif
            </p>
        </section>

        <div class="stack">
            <section class="card">
                <h2 style="margin-bottom:12px">Cargar nuevo documento</h2>
                <a class="dropzone" href="{{ route($area . '.documentos', ['modal' => 'subir']) }}" style="text-decoration:none">
                    <x-icon name="upload" size="24" />
                    <b>Haz clic para subir archivos</b>
                    <span>PDF, DOCX, XLSX, ZIP, imágenes</span>
                    <span class="btn btn-sm">Seleccionar archivo</span>
                </a>
            </section>
            <section class="card">
                <h2 style="margin-bottom:4px">Estado documental RUP por proyecto</h2>
                <p class="hint" style="margin-bottom:10px">Contrato, acta de inicio, acta de liquidación y certificado de experiencia</p>
                @foreach ($rup as $r)
                    <div class="rup-row"><span>{{ $r['project']->name }}</span><b @class(['ok' => $r['done'] === 4])>{{ $r['done'] }}/4</b></div>
                @endforeach
            </section>
        </div>
    </div>

    @if ($modal)
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="up-title" method="post" enctype="multipart/form-data" action="{{ route($area . '.documentos.subir') }}">
                @csrf
                <input type="hidden" name="_form" value="subir">
                <div class="modal-head">
                    <h2 id="up-title">Subir documento</h2>
                    <a class="btn btn-icon" href="{{ route($area . '.documentos') }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="field">
                        <label for="project_id">Proyecto</label>
                        <select id="project_id" name="project_id">
                            @foreach ($projects as $p)<option value="{{ $p->id }}" @selected((int) old('project_id') === $p->id)>{{ $p->code }} · {{ $p->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="type">Tipo de documento</label>
                        <select id="type" name="type">
                            @foreach (array_keys(FinanceStore::DOC_TYPES) as $type)<option @selected(old('type', 'Soporte de pago') === $type)>{{ $type }}</option>@endforeach
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
                    <a class="btn" href="{{ route($area . '.documentos') }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Subir</button>
                </div>
            </form>
        </div>
    @endif
@endsection
