@extends('layouts.app')
@section('title', 'Entidades')
@section('content')
    @php
        use App\Http\Controllers\EntityController;
        use App\Support\EntityStore;
        $isVic = auth()->user()->role === 'vicerrectoria';
        $url = fn (array $extra = []) => route('entidades.index', array_filter(['filtro' => $filter === 'todas' ? null : $filter, 'q' => $q] + $extra));
        $active = $all->where('active', true);
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">{{ $isVic ? 'FET · Vicerrectoría de Investigación y Extensión' : 'FET · Dirección Financiera' }}</div>
            <h1>Entidades contratantes y aliadas</h1>
            <p class="sub">Alcaldías, gobernación, empresas y aliados con los que se firman convenios y contratos, con su documentación legal</p>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="{{ $url(['modal' => 'nueva']) }}"><x-icon name="plus" size="16" />Nueva entidad</a>
            <x-notifications />
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="bank" size="16" /></span>Entidades activas</span>
            <span class="stat-value">{{ $active->count() }}</span>
            <span class="stat-foot">{{ $all->count() - $active->count() }} inactivas</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="checkc" size="16" /></span>Documentación completa</span>
            <span class="stat-value green">{{ $active->where('complete', true)->count() }}</span>
            <span class="stat-foot">Listas para firmar convenios</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon amber"><x-icon name="warning" size="16" /></span>Con documentos pendientes</span>
            <span class="stat-value">{{ $active->where('complete', false)->count() }}</span>
            <span class="stat-foot">Falta RUT u otro documento obligatorio</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon blue"><x-icon name="folder" size="16" /></span>Proyectos vinculados</span>
            <span class="stat-value">{{ $all->sum(fn ($e) => $e['projects']->count()) }}</span>
            <span class="stat-foot">Activos, liquidados y propuestos</span>
        </div>
    </div>

    <div class="toolbar">
        <form class="search" method="get" action="{{ route('entidades.index') }}" role="search">
            <x-icon name="search" size="16" />
            <label for="q" class="sr-only">Buscar entidades</label>
            <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Buscar por nombre, NIT o municipio…">
            @if ($filter !== 'todas')<input type="hidden" name="filtro" value="{{ $filter }}">@endif
        </form>
        <nav class="tabs" aria-label="Filtrar entidades">
            @foreach (EntityController::FILTERS as $key => $label)
                <a href="{{ route('entidades.index', array_filter(['filtro' => $key === 'todas' ? null : $key, 'q' => $q])) }}" @class(['on' => $filter === $key])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <section class="card">
        @if ($list->isEmpty())
            <p class="empty">No se encontraron entidades{{ $q ? " para «{$q}»" : '' }}.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Entidad</th><th>Tipo</th><th>Municipio</th><th>Proyectos</th><th>Documentos</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                    <tbody>
                    @foreach ($list as $e)
                        <tr @class(['selected' => $viewing && $viewing['id'] === $e['id']])>
                            <td style="min-width:220px"><a class="title row-link" href="{{ $url(['ver' => $e['id']]) }}">{{ $e['name'] }}</a><div class="meta">NIT {{ $e['nit'] }}</div></td>
                            <td>{{ $e['type_label'] }}</td>
                            <td>{{ $e['city'] }}</td>
                            <td>{{ $e['projects']->count() }}</td>
                            <td><span class="badge {{ $e['complete'] ? 'green' : 'amber' }}">{{ $e['done'] }} de {{ $e['checklist']->count() }}</span></td>
                            <td><span class="badge {{ $e['active'] ? 'green' : 'gray' }}">{{ $e['active'] ? 'Activa' : 'Inactiva' }}</span></td>
                            <td class="num">
                                <a class="btn btn-sm" href="{{ $url(['ver' => $e['id']]) }}">Ver<span class="sr-only"> {{ $e['name'] }}</span></a>
                                <a class="btn btn-sm" href="{{ $url(['editar' => $e['id']]) }}"><x-icon name="edit" size="14" />Editar<span class="sr-only"> {{ $e['name'] }}</span></a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Ficha de la entidad: datos, proyectos y documentos --}}
    @if ($viewing && ! $uploading)
        @php $e = $viewing; $close = $url(); @endphp
        <div class="modal-backdrop">
            <div class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="ent-title">
                <div class="modal-head">
                    <div>
                        <h2 id="ent-title">{{ $e['name'] }}</h2>
                        <p>NIT {{ $e['nit'] }} · {{ $e['type_label'] }} · {{ $e['city'] }} · {{ $e['active'] ? 'Activa' : 'Inactiva' }}</p>
                    </div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="grid-2">
                        <dl class="kv">
                            <dt>Representante legal</dt><dd>{{ $e['legal_rep'] ?: '—' }}</dd>
                            <dt>Dirección</dt><dd>{{ $e['address'] ?: '—' }}</dd>
                            <dt>Teléfono</dt><dd>{{ $e['phone'] ?: '—' }}</dd>
                            <dt>Correo</dt><dd>{{ $e['email'] ?: '—' }}</dd>
                        </dl>
                        <dl class="kv">
                            <dt>Contacto</dt><dd>{{ $e['contact_name'] ?: '—' }}</dd>
                            <dt>Correo del contacto</dt><dd>{{ $e['contact_email'] ?: '—' }}</dd>
                            <dt>Teléfono del contacto</dt><dd>{{ $e['contact_phone'] ?: '—' }}</dd>
                        </dl>
                    </div>

                    <h3 class="section-title">Documentos obligatorios · {{ $e['done'] }} de {{ $e['checklist']->count() }}</h3>
                    <ul class="steps">
                        @foreach ($e['checklist'] as $i => $c)
                            <li><span class="step {{ $c['done'] ? 'done' : 'next' }}">@if($c['done'])<x-icon name="check" size="12" />@else{{ $i + 1 }}@endif</span>
                                <span><b style="font-weight:500">{{ $c['type'] }}</b><span class="meta">{{ $c['done'] ? 'Cargado' : 'Pendiente' }} · {{ EntityStore::DOC_TYPES[$c['type']] }}</span></span></li>
                        @endforeach
                    </ul>

                    <h3 class="section-title">Documentos cargados</h3>
                    @forelse ($documents as $doc)
                        <div class="doc">
                            <span class="ext {{ strtolower($doc['ext']) }}" aria-hidden="true">{{ $doc['ext'] }}</span>
                            <div class="info">
                                <b>{{ $doc['type'] }}</b>
                                <span>{{ $doc['name'] }} · {{ $doc['by'] }} · {{ date_es($doc['date']) }} · {{ number_format($doc['size'], 1, ',', '.') }} MB</span>
                            </div>
                            @if ($doc['path'])
                                <a class="btn btn-sm" href="{{ route('entidades.documentos.descargar', [$doc['id'], 'ver' => 1]) }}" target="_blank" rel="noopener">Ver</a>
                                <a class="btn btn-sm" href="{{ route('entidades.documentos.descargar', $doc['id']) }}"><x-icon name="download" size="14" />Descargar</a>
                            @else
                                <span class="badge gray" title="Documento de ejemplo: no tiene archivo">De ejemplo</span>
                            @endif
                        </div>
                    @empty
                        <p class="empty">Aún no hay documentos. Empieza por el RUT.</p>
                    @endforelse

                    <h3 class="section-title">Proyectos con esta entidad</h3>
                    @forelse ($e['projects'] as $p)
                        <p>
                            @if ($isVic && $p->status !== 'propuesto')
                                <a href="{{ route('vic.proyectos.show', $p) }}">{{ $p->code }} · {{ $p->name }}</a>
                            @elseif (! $isVic && $p->status !== 'propuesto')
                                <a href="{{ route('fin.proyecto', $p) }}">{{ $p->code }} · {{ $p->name }}</a>
                            @else
                                {{ $p->code }} · {{ $p->name }}
                            @endif
                            <span class="badge {{ $p->statusTone() }}">{{ $p->statusLabel() }}</span>
                        </p>
                    @empty
                        <p class="empty">Todavía no tiene proyectos.</p>
                    @endforelse
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $close }}">Cerrar</a>
                    <a class="btn" href="{{ $url(['editar' => $e['id']]) }}"><x-icon name="edit" size="16" />Editar datos</a>
                    <a class="btn btn-primary" href="{{ $url(['ver' => $e['id'], 'modal' => 'subir']) }}"><x-icon name="upload" size="16" />Subir documento</a>
                </div>
            </div>
        </div>
    @endif

    {{-- Subir documento de la entidad --}}
    @if ($uploading)
        @php $e = $viewing; $missing = $e['checklist']->where('done', false)->pluck('type'); @endphp
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="up-title" method="post" enctype="multipart/form-data" action="{{ route('entidades.documentos.subir', $e['id']) }}">
                @csrf
                <input type="hidden" name="_form" value="subir-documento">
                <div class="modal-head">
                    <div><h2 id="up-title">Subir documento</h2><p>{{ $e['name'] }} · NIT {{ $e['nit'] }}</p></div>
                    <a class="btn btn-icon" href="{{ $url(['ver' => $e['id']]) }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="field">
                        <label for="doc-type">Tipo de documento *</label>
                        <select id="doc-type" name="type">
                            @foreach (EntityStore::DOC_TYPES as $type => $help)
                                <option value="{{ $type }}" @selected(old('type', $missing->first() ?? 'RUT') === $type)>{{ $type }}{{ $missing->contains($type) ? ' (pendiente)' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="doc-file">Archivo *</label>
                        <input id="doc-file" name="file" type="file" accept=".pdf,.png,.jpg,.jpeg" required style="height:auto;padding:8px">
                        @error('file')<span class="error">{{ $message }}</span>@enderror
                        <span class="modal-note">PDF o imagen, hasta 1,5 MB en esta demo.</span>
                    </div>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $url(['ver' => $e['id']]) }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Subir documento</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Nueva entidad / editar --}}
    @if ($creating || $editing)
        @php
            $e = $editing ?? ['name' => '', 'nit' => '', 'type' => 'publica', 'city' => '', 'address' => '', 'phone' => '', 'email' => '',
                'legal_rep' => '', 'contact_name' => '', 'contact_email' => '', 'contact_phone' => '', 'active' => true];
            $close = $editing ? $url(['ver' => $editing['id']]) : $url();
        @endphp
        <div class="modal-backdrop">
            <form class="modal modal-wide" role="dialog" aria-modal="true" aria-labelledby="ne-title" method="post"
                  action="{{ $editing ? route('entidades.update', $editing['id']) : route('entidades.store') }}">
                @csrf
                @if ($editing) @method('PUT') @else <input type="hidden" name="_form" value="nueva-entidad"> @endif
                <div class="modal-head">
                    <div><h2 id="ne-title">{{ $editing ? 'Editar entidad' : 'Nueva entidad' }}</h2>
                        <p>{{ $editing ? 'El nombre se actualiza en todos sus proyectos.' : 'Después de crearla podrás cargar su RUT y demás documentos.' }}</p></div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="field">
                        <label for="e-name">Razón social o nombre *</label>
                        <input id="e-name" name="name" value="{{ old('name', $e['name']) }}" placeholder="Alcaldía de …" required autofocus>
                        @error('name')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="grid-3-form">
                        <div class="field">
                            <label for="e-nit">NIT *</label>
                            <input id="e-nit" name="nit" value="{{ old('nit', $e['nit']) }}" placeholder="891.180.009-1" required>
                            @error('nit')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="e-type">Tipo *</label>
                            <select id="e-type" name="type">
                                @foreach (EntityStore::TYPES as $k => $label)<option value="{{ $k }}" @selected(old('type', $e['type']) === $k)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="e-city">Municipio *</label>
                            <input id="e-city" name="city" value="{{ old('city', $e['city']) }}" required>
                            @error('city')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="grid-3-form">
                        <div class="field">
                            <label for="e-address">Dirección</label>
                            <input id="e-address" name="address" value="{{ old('address', $e['address']) }}">
                        </div>
                        <div class="field">
                            <label for="e-phone">Teléfono</label>
                            <input id="e-phone" name="phone" value="{{ old('phone', $e['phone']) }}">
                        </div>
                        <div class="field">
                            <label for="e-email">Correo</label>
                            <input id="e-email" name="email" type="email" value="{{ old('email', $e['email']) }}">
                            @error('email')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="field">
                        <label for="e-rep">Representante legal</label>
                        <input id="e-rep" name="legal_rep" value="{{ old('legal_rep', $e['legal_rep']) }}">
                    </div>
                    <h3 class="section-title">Persona de contacto</h3>
                    <div class="grid-3-form">
                        <div class="field">
                            <label for="e-cname">Nombre</label>
                            <input id="e-cname" name="contact_name" value="{{ old('contact_name', $e['contact_name']) }}">
                        </div>
                        <div class="field">
                            <label for="e-cemail">Correo</label>
                            <input id="e-cemail" name="contact_email" type="email" value="{{ old('contact_email', $e['contact_email']) }}">
                            @error('contact_email')<span class="error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="e-cphone">Teléfono</label>
                            <input id="e-cphone" name="contact_phone" value="{{ old('contact_phone', $e['contact_phone']) }}">
                        </div>
                    </div>
                    <label class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $e['active']))>Entidad activa (se puede elegir al crear proyectos)</label>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $close }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">{{ $editing ? 'Guardar cambios' : 'Crear entidad' }}</button>
                </div>
            </form>
        </div>
    @endif
@endsection
