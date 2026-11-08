@extends('layouts.app')
@section('title', 'Presupuesto · ' . $project->name)
@section('content')
    @include('vic.projects._header')
    @include('partials.project-budget', [
        'openTransfer' => route($area . '.proyectos.presupuesto', [$project, 'modal' => 'traslado']),
        'closeUrl' => route($area . '.proyectos.presupuesto', $project),
        'movementsUrl' => route($area . '.proyectos.movimientos', $project),
        'transferAction' => route($area . '.proyectos.traslado', $project),
        'transferButton' => $area === 'vic' ? 'Aprobar traslado' : 'Solicitar traslado',
        'transferNote' => $area === 'vic' ? 'Queda aprobado de inmediato y registrado en la trazabilidad.' : 'Queda registrado en la trazabilidad y requiere aprobación de Vicerrectoría.',
    ])
@endsection
