@extends('layouts.app')
@section('title', 'Centro de alertas')
@section('content')
    @php use App\Http\Controllers\Fin\FinanceController; @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Dirección Financiera</div>
            <h1>Centro de alertas</h1>
            <p class="sub">Sobrecostos, plazos, entregas vencidas y reportes pendientes, generados automáticamente</p>
        </div>
        <div class="actions">
            <x-notifications />
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="bell" size="16" /></span>Total</span>
            <span class="stat-value">{{ $all->count() }}</span>
            <span class="stat-foot">Alertas activas</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon red"><x-icon name="warning" size="16" /></span>Críticas</span>
            <span class="stat-value">{{ $all->where('severity', 'critica')->count() }}</span>
            <span class="stat-foot">Sobrecostos y vencimientos</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon amber"><x-icon name="warning" size="16" /></span>Advertencias</span>
            <span class="stat-value">{{ $all->where('severity', 'advertencia')->count() }}</span>
            <span class="stat-foot">Plazos y rezagos</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon blue"><x-icon name="report" size="16" /></span>Informativas</span>
            <span class="stat-value">{{ $all->where('severity', 'informativa')->count() }}</span>
            <span class="stat-foot">Reportes pendientes</span>
        </div>
    </div>

    <section class="card">
        <div class="card-head" style="align-items:center">
            <h2>Alertas</h2>
            <nav class="tabs" aria-label="Filtrar por nivel">
                <a href="{{ route('fin.alertas') }}" @class(['on' => ! $level])>Todas ({{ $all->count() }})</a>
                @foreach (FinanceController::ALERT_TABS as $key => $label)
                    <a href="{{ route('fin.alertas', ['nivel' => $key]) }}" @class(['on' => $level === $key])>{{ $label }} ({{ $all->where('severity', $key)->count() }})</a>
                @endforeach
            </nav>
        </div>
        <div class="alert-list">
            @forelse ($list as $a)
                <x-alert-item :alert="$a" :href="route('fin.proyecto', $a->project_id)" />
            @empty
                <p class="empty">No hay alertas de este nivel.</p>
            @endforelse
        </div>
    </section>
@endsection
