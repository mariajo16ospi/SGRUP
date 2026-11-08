<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · SGRUP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sgrup.css') }}?v={{ filemtime(public_path('css/sgrup.css')) }}">
</head>
<body class="print-page">
<div class="print-toolbar">
    <a class="btn" href="{{ url()->previous() }}">← Volver</a>
    <button class="btn btn-primary" type="button" onclick="window.print()"><x-icon name="printer" size="16" />Imprimir o guardar como PDF</button>
</div>
<main class="sheet">
    <header class="sheet-head">
        <img src="{{ asset('img/logo-fet.png') }}" alt="Fundación Escuela Tecnológica de Neiva “Jesús Oviedo Pérez”">
        <div>
            <b>Vicerrectoría de Investigación y Extensión</b>
            <span>Fundación Escuela Tecnológica de Neiva “Jesús Oviedo Pérez” · Neiva, Huila</span>
        </div>
    </header>
    @yield('content')
</main>
</body>
</html>
