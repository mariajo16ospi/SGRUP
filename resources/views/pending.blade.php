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
        <h2 style="margin-bottom:6px">Esta sección todavía no está disponible</h2>
        <p>En esta versión de demostración están construidas las pantallas de los cuatro roles: Investigador, Vicerrectoría, Área financiera y Administración.</p>
        <p style="margin-top:14px"><a class="btn btn-primary" href="{{ auth()->user()->homeRoute() }}">Ir a mi página de inicio</a></p>
    </div>
@endsection
