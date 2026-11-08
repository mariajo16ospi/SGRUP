@extends('layouts.app')
@section('title', 'Presupuesto · ' . $project->name)
@section('content')
    <div class="page-head" style="border-bottom:1px solid var(--line);padding-bottom:16px">
        <div>
            <div class="crumb">FET · Dirección Financiera <x-icon name="chevron" size="12" /> <a href="{{ route('fin.presupuesto') }}">Control presupuestario</a></div>
            <h1>{{ $project->name }}</h1>
            <p class="sub">{{ $project->contractLabel() }} {{ $project->code }} · {{ $project->entity }} · Coord.: {{ $project->coordinator->name }} · {{ date_es($project->start_date) }} – {{ date_es($project->end_date) }}</p>
        </div>
        <div class="actions">
            <button class="btn" type="button" onclick="window.print()"><x-icon name="printer" size="16" />Informe PDF</button>
        </div>
    </div>

    @include('partials.project-budget', [
        'openTransfer' => route('fin.proyecto', [$project, 'modal' => 'traslado']),
        'closeUrl' => route('fin.proyecto', $project),
        'movementsUrl' => route('fin.proyecto.movimientos', $project),
        'transferAction' => route('fin.proyecto.traslado', $project),
        'transferButton' => 'Solicitar traslado',
        'transferNote' => 'Queda registrado en la trazabilidad y requiere aprobación de Vicerrectoría.',
    ])
@endsection
