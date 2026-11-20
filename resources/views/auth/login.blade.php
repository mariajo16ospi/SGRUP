<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · SGRUP</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('img/icon-512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sgrup.css') }}?v={{ filemtime(public_path('css/sgrup.css')) }}">
</head>
<body>
@php
    $roleTone = ['investigador' => 'green', 'vicerrectoria' => 'yellow', 'financiero' => 'blue', 'admin' => 'gray'];
@endphp
<div class="login">
    <section class="login-hero">
        <div class="ring" aria-hidden="true"></div>
        <div class="logo"><img src="{{ asset('img/logo-fet.png') }}" alt="Fundación Escuela Tecnológica de Neiva “Jesús Oviedo Pérez”"></div>
        <div class="content">
            <div class="eyebrow">Vicerrectoría de Investigación y Extensión</div>
            <h1>Sistema de Gestión y Seguimiento de Proyectos</h1>
            <p>Control técnico, financiero y documental de los proyectos de investigación y extensión, convenios y contratos con alcaldías, gobernación y entidades privadas.</p>
            <div class="hero-stats">
                <div><b>{{ $activeCount }}</b><span>Proyectos activos</span></div>
                <div><b>{{ money_m($budget) }}</b><span>Presupuesto gestionado</span></div>
                <div><b>{{ $liquidated }}</b><span>Proyectos liquidados</span></div>
            </div>
            <div class="features">
                @foreach (['Seguimiento técnico', 'Curva S', 'Compras por roles', 'Gestión documental', 'Trazabilidad'] as $f)
                    <span><x-icon name="checkc" size="14" />{{ $f }}</span>
                @endforeach
            </div>
        </div>
        <footer>© {{ date('Y') }} Fundación Escuela Tecnológica de Neiva “Jesús Oviedo Pérez” · Neiva, Huila</footer>
    </section>

    <section class="login-form">
        <form method="post" action="{{ route('login.store') }}">
            @csrf
            <h2>Iniciar sesión</h2>
            <br><br>
            @error('user_id')<div class="flash err" role="alert">{{ $message }}</div>@enderror
            <fieldset style="border:0;padding:0;margin:0">
                <legend class="sr-only">Usuario</legend>
                @foreach ($users as $u)
                    <label class="user-option">
                        <input type="radio" name="user_id" value="{{ $u->id }}" @checked(old('user_id', $users->first()->id) == $u->id)>
                        <span class="box">
                            <span class="avatar" aria-hidden="true">{{ $u->initials() }}</span>
                            <span class="info">
                                <b>{{ $u->name }}</b>
                                <span>{{ $u->email }}</span>
                                @if ($u->role_description)<span class="desc">{{ $u->role_description }}</span>@endif
                            </span>
                            <span class="badge {{ $roleTone[$u->role] ?? 'gray' }}">{{ $u->roleLabel() }}</span>
                        </span>
                    </label>
                @endforeach
            </fieldset>
            <button class="btn btn-primary" type="submit">Ingresar <x-icon name="arrow" size="16" /></button>
        </form>
    </section>
</div>
</body>
</html>
