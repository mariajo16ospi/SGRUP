@extends('layouts.app')
@section('title', 'Reportes y experiencia')
@section('content')
    @php
        $cards = [
            ['Consolidado de proyectos', 'Estado técnico y financiero de todos los proyectos (Excel/CSV)', route('vic.reportes.export', 'consolidado'), 'folder'],
            ['Ejecución presupuestal por rubro', 'Asignado, ejecutado, comprometido y disponible (CSV)', route('vic.reportes.export', 'rubros'), 'wallet'],
            ['Trazabilidad de compras', 'Todas las solicitudes con su flujo de aprobación (CSV)', route('vic.reportes.export', 'compras'), 'cart'],
            ['Experiencia institucional (RUP)', 'Contratos y convenios ejecutados para la Cámara de Comercio', route('vic.reportes.export', 'experiencia'), 'idcard'],
            ['Informe de gestión imprimible', 'Versión para imprimir o guardar como PDF', route('vic.reportes.informe'), 'printer'],
        ];
        $max = max(1, $byEntity->max());
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Vicerrectoría de Investigación y Extensión</div>
            <h1>Reportes y experiencia institucional</h1>
            <p class="sub">Consolidación de información para la toma de decisiones y la certificación de experiencia</p>
        </div>
        <div class="actions"><x-notifications /></div>
    </div>

    <div class="grid-3" style="margin-bottom:20px">
        @foreach ($cards as [$title, $desc, $url, $icon])
            <div class="card report-card">
                <span class="stat-icon"><x-icon :name="$icon" size="16" /></span>
                <h2>{{ $title }}</h2>
                <p class="hint">{{ $desc }}</p>
                <a class="btn btn-sm" href="{{ $url }}"><x-icon name="download" size="14" />Generar<span class="sr-only"> {{ $title }}</span></a>
            </div>
        @endforeach
    </div>

    <div class="cols" style="grid-template-columns:minmax(0,1fr) 340px">
        <section class="card">
            <div class="card-head">
                <div><h2>Experiencia institucional consolidada</h2><p class="hint">Contratos y convenios ejecutados por la FET</p></div>
                <a class="btn btn-sm" href="{{ route('vic.reportes.export', 'experiencia') }}"><x-icon name="download" size="14" />Descargar</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Código</th><th>Objeto</th><th>Contratante</th><th>Vigencia</th><th class="num">Valor</th><th>Estado</th></tr></thead>
                    <tbody>
                    @foreach ($projects as $p)
                        <tr>
                            <td class="strong" style="white-space:nowrap">{{ $p->code }}</td>
                            <td style="min-width:160px"><a class="row-link" href="{{ route('vic.proyectos.show', $p) }}">{{ $p->name }}</a></td>
                            <td>{{ $p->entity }}</td>
                            <td style="white-space:nowrap">{{ date_es($p->start_date) }} – {{ date_es($p->end_date) }}</td>
                            <td class="num">{{ money($p->value) }}</td>
                            <td><span class="badge {{ $p->status === 'liquidado' ? 'gray' : 'green' }}">{{ $p->status === 'liquidado' ? 'Liquidado' : 'En ejecución' }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mini-stats">
                <div><span>Contratos/convenios</span><b>{{ $projects->count() }}</b></div>
                <div><span>Valor total gestionado</span><b>{{ money_m($total) }}</b></div>
                <div><span>Liquidados (certificables)</span><b>{{ $liquidated }}</b></div>
            </div>
        </section>
        <section class="card">
            <h2 style="margin-bottom:14px">Valor por entidad contratante</h2>
            @foreach ($byEntity as $entity => $value)
                <div class="line-bar"><span>{{ $entity }}</span><span class="strong">{{ money_m($value) }}</span><div class="bar"><i class="dark" style="width:{{ $value / $max * 100 }}%"></i></div></div>
            @endforeach
        </section>
    </div>
@endsection
