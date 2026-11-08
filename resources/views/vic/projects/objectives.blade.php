@extends('layouts.app')
@section('title', 'Objetivos y productos · ' . $project->name)
@section('content')
    @php use App\Support\ProjectTracking; @endphp
    @include('vic.projects._header')

    <div class="cols" style="grid-template-columns:minmax(0,1fr) 320px">
        <section class="card">
            <div class="card-head">
                <div>
                    <h2>Objetivos y productos</h2>
                    <p class="hint">Objetivo: {{ $project->general_objective }}</p>
                </div>
                @if ($project->status === 'activo')
                    <a class="btn" href="{{ route($area . '.proyectos.objetivos', [$project, 'modal' => 'producto']) }}"><x-icon name="plus" size="16" />Agregar producto</a>
                @endif
            </div>
            @if ($products->isEmpty())
                <p class="empty">Este proyecto aún no tiene productos registrados.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Producto / meta</th><th>Fecha de entrega</th><th>Cantidad</th><th>Avance</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                        <tbody>
                        @foreach ($products as $p)
                            <tr>
                                <td style="min-width:180px"><div class="title">{{ $p['name'] }}</div><div class="meta">{{ $p['type'] }}</div></td>
                                <td style="white-space:nowrap">{{ date_es($p['due']) }}</td>
                                <td style="white-space:nowrap">{{ number_format($p['done'], 0, ',', '.') }} / {{ number_format($p['target'], 0, ',', '.') }} {{ $p['unit'] }}</td>
                                <td style="min-width:110px"><div class="strong" style="font-size:12px">{{ $p['progress'] }}%</div><div class="bar"><i class="{{ $p['status'] === 'vencido' ? 'red' : '' }}" style="width:{{ $p['progress'] }}%"></i></div></td>
                                <td><span class="badge {{ $p['tone'] }}">{{ $p['status'] === 'vencido' ? 'Vencido' : ($p['status'] === 'proximo' ? $p['status_label'] : $p['status_label']) }}</span></td>
                                <td class="num">
                                    @if ($area === 'inv' && $project->status === 'activo')
                                        <a class="btn btn-sm" href="{{ route('inv.proyectos.objetivos', [$project, 'actualizar' => $p['id']]) }}">Actualizar<span class="sr-only"> {{ $p['name'] }}</span></a>
                                    @else
                                        <a class="strong" href="{{ route($area . '.proyectos.avances', $project) }}">Ver avance<span class="sr-only"> de {{ $p['name'] }}</span></a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card">
            <h2 style="margin-bottom:12px">Cumplimiento de metas</h2>
            @php $c = 2 * M_PI * 48; @endphp
            <div style="display:flex;flex-direction:column;align-items:center;gap:6px;margin-bottom:16px">
                <svg width="128" height="128" viewBox="0 0 128 128" role="img" aria-label="Cumplimiento total {{ $compliance }} %">
                    <circle cx="64" cy="64" r="48" fill="none" stroke="#e6e9e5" stroke-width="14" />
                    <circle cx="64" cy="64" r="48" fill="none" stroke="#137a26" stroke-width="14" stroke-linecap="round" stroke-dasharray="{{ $c * $compliance / 100 }} {{ $c }}" transform="rotate(-90 64 64)" />
                    <text x="64" y="66" text-anchor="middle" style="font-size:26px;font-weight:700;fill:#17201b">{{ $compliance }}%</text>
                    <text x="64" y="84" text-anchor="middle" style="font-size:11px;fill:#5b655f">total</text>
                </svg>
                <span class="hint">Promedio de {{ $products->count() }} {{ $products->count() === 1 ? 'producto' : 'productos' }}</span>
            </div>
            <h3 style="font-size:13px;margin-bottom:10px">Avance por tipo de producto</h3>
            @foreach ($byType as $type => $pct)
                <div class="line-bar"><span>{{ $type }}</span><span class="strong">{{ $pct }}%</span><div class="bar"><i style="width:{{ $pct }}%"></i></div></div>
            @endforeach
        </section>
    </div>

    @if ($updating)
        @php $close = route('inv.proyectos.objetivos', $project); @endphp
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="upd-title" method="post" action="{{ route('inv.proyectos.productos.update', [$project, $updating['id']]) }}">
                @csrf
                @method('PUT')
                <div class="modal-head">
                    <div><h2 id="upd-title">Actualizar avance del producto</h2><p>{{ $updating['name'] }} · entrega {{ date_es($updating['due']) }}</p></div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="field">
                        <label for="done">Cantidad acumulada lograda (meta: {{ $updating['target'] }} {{ $updating['unit'] }})</label>
                        <input id="done" name="done" type="number" min="0" value="{{ old('done', $updating['done']) }}" required autofocus>
                        @error('done')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <p class="modal-note">El avance técnico del proyecto se recalcula con el promedio de sus productos. Recuerda subir las evidencias en Documentos.</p>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $close }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Guardar avance</button>
                </div>
            </form>
        </div>
    @endif

    @if ($modal)
        @php $close = route($area . '.proyectos.objetivos', $project); @endphp
        <div class="modal-backdrop">
            <form class="modal" role="dialog" aria-modal="true" aria-labelledby="pr-title" method="post" action="{{ route($area . '.proyectos.productos.store', $project) }}">
                @csrf
                <input type="hidden" name="_form" value="producto">
                <div class="modal-head">
                    <div><h2 id="pr-title">Agregar producto</h2><p>{{ $project->name }}</p></div>
                    <a class="btn btn-icon" href="{{ $close }}" aria-label="Cerrar"><x-icon name="x" /></a>
                </div>
                <div class="modal-body">
                    <div class="field">
                        <label for="name">Producto *</label>
                        <input id="name" name="name" value="{{ old('name') }}" placeholder="Ej.: Capacitación a productores" required autofocus>
                        @error('name')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="row">
                        <div class="field">
                            <label for="type">Tipo</label>
                            <select id="type" name="type">@foreach (ProjectTracking::PRODUCT_TYPES as $t)<option @selected(old('type') === $t)>{{ $t }}</option>@endforeach</select>
                        </div>
                        <div class="field">
                            <label for="due">Fecha de entrega *</label>
                            <input id="due" name="due" type="date" value="{{ old('due') }}" min="{{ $project->start_date->toDateString() }}" max="{{ $project->end_date->toDateString() }}" required>
                            @error('due')<span class="error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="row">
                        <div class="field">
                            <label for="target">Meta *</label>
                            <input id="target" name="target" type="number" min="1" value="{{ old('target', 1) }}" required>
                        </div>
                        <div class="field">
                            <label for="unit">Unidad</label>
                            <input id="unit" name="unit" value="{{ old('unit', 'und.') }}" required>
                        </div>
                    </div>
                </div>
                <div class="modal-foot">
                    <a class="btn" href="{{ $close }}">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Agregar producto</button>
                </div>
            </form>
        </div>
    @endif
@endsection
