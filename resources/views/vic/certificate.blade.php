@extends('layouts.print')
@section('title', 'Certificación de experiencia')
@section('content')
    <h1 style="text-align:center;margin-top:24px">Certificación de experiencia</h1>
    <p style="text-align:center" class="muted">Para el Registro Único de Proponentes (RUP) · Cámara de Comercio</p>

    <p style="margin-top:28px;line-height:1.7">
        La Vicerrectoría de Investigación y Extensión de la Fundación Escuela Tecnológica de Neiva “Jesús Oviedo Pérez”
        certifica que la institución ejecutó el {{ strtolower($project->contractLabel()) }} <b>{{ $project->code }}</b>
        suscrito con <b>{{ $project->entity }}</b>, cuyo objeto fue <b>{{ $project->name }}</b>, con la siguiente información:
    </p>

    <table style="margin:18px 0">
        <tbody>
        <tr><th>Contratante</th><td>{{ $project->entity }}</td></tr>
        <tr><th>Número</th><td>{{ $project->code }}</td></tr>
        <tr><th>Objeto</th><td>{{ $project->general_objective }}</td></tr>
        <tr><th>Fecha de inicio</th><td>{{ date_es($project->start_date) }}</td></tr>
        <tr><th>Fecha de terminación</th><td>{{ date_es($project->end_date) }}</td></tr>
        <tr><th>Valor ejecutado</th><td>{{ money($project->executed_amount) }} de {{ money($project->value) }}</td></tr>
        <tr><th>Estado</th><td>{{ $project->statusLabel() }}</td></tr>
        </tbody>
    </table>

    <h2>Productos entregados</h2>
    <ul>
        @foreach ($products as $p)
            <li>{{ $p['name'] }} — {{ number_format($p['done'], 0, ',', '.') }} {{ $p['unit'] }} ({{ $p['progress'] }}%)</li>
        @endforeach
    </ul>

    <p style="margin-top:28px">Se expide en Neiva, Huila, el {{ date_es(today()) }}.</p>
    <div class="signature">
        <span></span>
        <b>Dra. Marcela Trujillo</b>
        <span class="muted">Vicerrectoría de Investigación y Extensión</span>
    </div>
    <p class="hint" style="margin-top:28px">Documento generado por SGRUP · versión de demostración.</p>
@endsection
