@extends('layouts.app')
@section('title', request('s', 'En construcción'))
@section('content')
    <div class="page-head">
        <div>
            <div class="crumb">FET · Vicerrectoría de Investigación y Extensión</div>
            <h1>{{ request('s', auth()->user()->roleLabel()) }}</h1>
        </div>
    </div>
    <div class="card empty">
        <h2 style="margin-bottom:6px">Esta sección se construirá en la siguiente fase</h2>
        <p>Ya está diseñada en los mockups. Por ahora están disponibles el inicio de sesión y las pantallas de Vicerrectoría: Aprobaciones, Panel general, Proyectos y contratos y el resumen de cada proyecto.</p>
        @if (auth()->user()->role === 'vicerrectoria')
            <p style="margin-top:14px"><a class="btn btn-primary" href="{{ route('vic.panel') }}">Ir al panel general</a></p>
        @endif
    </div>
@endsection
