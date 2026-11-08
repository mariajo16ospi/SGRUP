@extends('layouts.app')
@section('title', 'Compras · ' . $project->name)
@section('content')
    @include('vic.projects._header')

    <section class="card">
        <div class="card-head">
            <div>
                <h2>Solicitudes de compra</h2>
                <p class="flow-hint" style="margin:2px 0 0">Flujo: Coordinador → Vicerrectoría → Área financiera → Pago</p>
            </div>
            <nav class="tabs" aria-label="Filtrar por estado">
                @foreach ($tabs as $key => $t)
                    <a href="{{ route($area . '.proyectos.compras', array_filter([$project->id, 'estado' => $key === 'todas' ? null : $key])) }}" @class(['on' => $tab === $key])>{{ $t['label'] }} ({{ $t['count'] }})</a>
                @endforeach
            </nav>
        </div>
        @if ($list->isEmpty())
            <p class="empty">No hay solicitudes en este estado.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Código</th><th>Descripción</th><th class="num">Valor</th><th>Solicitante</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
                    <tbody>
                    @foreach ($list as $p)
                        <tr>
                            <td style="white-space:nowrap"><div class="title">{{ $p->code }}</div><div class="meta">{{ date_es($p->submitted_at) }}</div></td>
                            <td style="min-width:200px"><div class="title">{{ $p->description }}</div><div class="meta">{{ $p->budgetLine->name }}</div></td>
                            <td class="num"><div class="strong">{{ money($p->value) }}</div>@if ($p->overrunPercent())<div class="meta text-red">Cotizado {{ money_m($p->original_value) }}</div>@endif</td>
                            <td style="white-space:nowrap"><span class="user-cell"><span class="avatar" aria-hidden="true">{{ $p->requester->initials() }}</span>{{ $p->requester->name }}</span></td>
                            <td>
                                <span class="badge {{ $p->statusTone() }}">{{ $p->statusLabel() }}</span>
                                @if ($p->overrunPercent() && $p->status === 'en_vicerrectoria')<span class="badge red">Excede rubro</span>@endif
                            </td>
                            <td class="num"><a class="btn btn-sm" href="{{ route($area . '.compras', ['ver' => $p->code]) }}">{{ $p->status === 'en_vicerrectoria' ? 'Revisar' : 'Ver' }}<span class="sr-only"> {{ $p->code }}</span></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
