@extends('layouts.app')
@section('title', 'Mis proyectos')
@section('content')
    @php
        $user = auth()->user();
        $first = \Illuminate\Support\Str::before($user->name, ' ');
        $inInvestment = $active->where('phase', 'inversion')->count();
        $inOperation = $active->where('phase', 'operacion')->count();
    @endphp
    <div class="page-head">
        <div>
            <div class="crumb">FET · Portal del investigador</div>
            <h1>Mis proyectos</h1>
            <p class="sub">Hola, {{ $first }}. Tienes {{ $todo->count() }} {{ $todo->count() === 1 ? 'pendiente' : 'pendientes' }} este mes · Corte: {{ date_es(today()) }}</p>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('inv.compras', ['modal' => 'nueva']) }}"><x-icon name="cart" size="16" />Nueva solicitud de compra</a>
            <a class="btn btn-primary" href="{{ route('inv.cargar') }}"><x-icon name="upload" size="16" />Cargar avance del mes</a>
            <a class="btn btn-icon" href="{{ route('inv.alertas') }}" aria-label="Alertas"><x-icon name="bell" /><span class="dot"></span></a>
        </div>
    </div>

    <div class="grid-4">
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon"><x-icon name="folder" size="16" /></span>Proyectos a mi cargo</span>
            <span class="stat-value">{{ $active->count() }}</span>
            <span class="stat-foot">{{ $inInvestment }} en inversión · {{ $inOperation }} en operación</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon amber"><x-icon name="calendar" size="16" /></span>Próximo reporte mensual</span>
            @if ($nextReport)
                <span class="stat-value">{{ $nextReport['due']->day }} {{ ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][$nextReport['due']->month - 1] }}</span>
                <span class="stat-foot">Avance de {{ \Illuminate\Support\Str::before(month_long($nextReport['period']), ' ') }} · {{ $nextReport['days'] < 0 ? 'vencido' : ($nextReport['days'] === 0 ? 'vence hoy' : 'faltan ' . $nextReport['days'] . ' ' . ($nextReport['days'] === 1 ? 'día' : 'días')) }}</span>
            @else
                <span class="stat-value green">Al día</span>
                <span class="stat-foot">No tienes avances por cargar</span>
            @endif
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon blue"><x-icon name="cart" size="16" /></span>Mis compras en trámite</span>
            <span class="stat-value">{{ $inProcess->count() }}</span>
            <span class="stat-foot">{{ $returned->count() }} {{ $returned->count() === 1 ? 'devuelta' : 'devueltas' }} para ajuste</span>
        </div>
        <div class="card stat">
            <span class="stat-label"><span class="stat-icon red"><x-icon name="warning" size="16" /></span>Entregas vencidas</span>
            <span class="stat-value {{ $overdue->isNotEmpty() ? 'red' : '' }}">{{ $overdue->count() }}</span>
            <span class="stat-foot">{{ $overdue->isNotEmpty() ? $overdue->first()['name'] . ' (' . $overdue->first()['progress'] . ' %)' : 'Sin entregas vencidas' }}</span>
        </div>
    </div>

    <section class="card" style="margin-bottom:20px">
        <h2>Mis pendientes</h2>
        <p class="hint" style="margin-bottom:12px">Lo que debes hacer para que tus proyectos sigan al día</p>
        @forelse ($todo as $t)
            <div class="todo">
                <div class="info"><b>{{ $t['title'] }}</b><span>{{ $t['detail'] }}</span></div>
                <span class="badge {{ $t['tone'] }}">{{ $t['badge'] }}</span>
                <a class="btn btn-sm" href="{{ $t['url'] }}">{{ $t['action'] }}</a>
            </div>
        @empty
            <p class="empty">No tienes pendientes. ¡Todo al día!</p>
        @endforelse
    </section>

    <div class="grid-2">
        @foreach ($cards as $c)
            @php $p = $c['project']; @endphp
            <article class="card project-card">
                <div class="tags">
                    <span class="badge yellow">{{ $p->phaseLabel() }}</span>
                    <span class="badge {{ $p->statusTone() }}">{{ $p->statusLabel() }}</span>
                </div>
                <div>
                    <h3>{{ $p->name }}</h3>
                    <div class="code">{{ $p->contractLabel() }} {{ $p->code }} · {{ $p->entity }}</div>
                </div>
                <div class="bars">
                    <div><span>Avance técnico <b>{{ $c['technical'] }} %</b></span><div class="bar"><i style="width:{{ $c['technical'] }}%"></i></div></div>
                    <div><span>Ejecución presupuestal <b>{{ $c['financial'] }} %</b></span><div class="bar"><i class="{{ $p->health === 'sobrecosto' ? 'red' : 'dark' }}" style="width:{{ min(100, $c['financial']) }}%"></i></div></div>
                </div>
                <footer>
                    <span><b>{{ $p->daysRemaining() }}</b> días restantes</span>
                    <a class="strong" href="{{ route('inv.proyectos.show', $p) }}">Abrir proyecto<span class="sr-only"> {{ $p->name }}</span> →</a>
                </footer>
            </article>
        @endforeach
    </div>
@endsection
