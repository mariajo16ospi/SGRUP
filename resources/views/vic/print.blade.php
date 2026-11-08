@extends('layouts.print')
@section('title', 'Informe de gestión')
@section('content')
    @php
        $budget = $totals->sum('value');
        $executed = $totals->sum('executed');
    @endphp
    <h1>Informe de gestión de proyectos</h1>
    <p class="muted">Corte: {{ date_es(today()) }} · {{ $totals->count() }} proyectos activos</p>

    <div class="mini-stats" style="grid-template-columns:repeat(4,1fr)">
        <div><span>Presupuesto activo</span><b>{{ money_m($budget) }}</b></div>
        <div><span>Ejecutado</span><b>{{ money_m($executed) }} ({{ $budget ? round($executed / $budget * 100) : 0 }}%)</b></div>
        <div><span>Productos entregados</span><b>{{ $products->where('status', 'entregado')->count() }} de {{ $products->count() }}</b></div>
        <div><span>Alertas críticas</span><b>{{ $alerts->where('severity', 'critica')->count() }}</b></div>
    </div>

    <h2>Estado por proyecto</h2>
    <table>
        <thead><tr><th>Proyecto</th><th>Fase</th><th class="num">Valor</th><th class="num">Ejecutado</th><th class="num">Ejec. %</th><th class="num">Planeado %</th><th class="num">Técnico %</th></tr></thead>
        <tbody>
        @foreach ($totals as $t)
            <tr>
                <td><b>{{ $t['project']->name }}</b><br><span class="meta">{{ $t['project']->code }} · {{ $t['project']->entity }}</span></td>
                <td>{{ $t['project']->phaseLabel() }}</td>
                <td class="num">{{ money($t['value']) }}</td>
                <td class="num">{{ money($t['executed']) }}</td>
                <td class="num">{{ $t['executed_pct'] }}%</td>
                <td class="num">{{ $t['planned_pct'] }}%</td>
                <td class="num">{{ $t['technical'] }}%</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Entregas vencidas</h2>
    <table>
        <thead><tr><th>Producto</th><th>Proyecto</th><th>Entrega</th><th class="num">Avance</th></tr></thead>
        <tbody>
        @forelse ($products->where('status', 'vencido') as $p)
            <tr><td>{{ $p['name'] }}</td><td>{{ $p['project']->name }}</td><td>{{ date_es($p['due']) }}</td><td class="num">{{ $p['progress'] }}%</td></tr>
        @empty
            <tr><td colspan="4">Sin entregas vencidas.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h2>Alertas críticas</h2>
    <ul>
        @foreach ($alerts->where('severity', 'critica') as $a)
            <li><b>{{ $a->title }}</b> — {{ $a->detail }}</li>
        @endforeach
    </ul>
@endsection
