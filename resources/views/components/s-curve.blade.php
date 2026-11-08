@props(['curve', 'label' => 'Curva S'])
@php
    use App\Support\SCurve;
    $todayX = $curve->todayX();
    $last = $curve->lastExecuted();
    $summary = $last
        ? $label . ': a ' . month_short($last['month']) . ' el acumulado proyectado es ' . round($last['planned']) . ' % y el ejecutado ' . round($last['executed']) . ' %'
        : $label;
@endphp
<svg class="chart" viewBox="0 0 {{ SCurve::W }} {{ SCurve::H }}" role="img" aria-label="{{ $summary }}">
    @foreach ([100, 75, 50, 25, 0] as $pct)
        <line x1="{{ SCurve::LEFT }}" x2="{{ SCurve::W - SCurve::RIGHT }}" y1="{{ $curve->y($pct) }}" y2="{{ $curve->y($pct) }}" stroke="{{ $pct === 0 ? '#cfd6cf' : '#e8ece7' }}" />
        <text x="{{ SCurve::LEFT - 8 }}" y="{{ $curve->y($pct) + 4 }}" text-anchor="end">{{ $pct }}%</text>
    @endforeach
    @foreach ($curve->xLabels() as $l)
        <text x="{{ $l['x'] }}" y="{{ SCurve::H - 12 }}" text-anchor="middle">{{ $l['label'] }}</text>
    @endforeach

    @if ($area = $curve->executedArea())
        <polygon points="{{ $area }}" fill="#137a26" fill-opacity=".08" />
    @endif
    @if ($todayX)
        <line x1="{{ $todayX }}" x2="{{ $todayX }}" y1="{{ SCurve::TOP }}" y2="{{ $curve->y(0) }}" stroke="#8a948d" stroke-dasharray="4 4" />
        <text x="{{ $todayX + 4 }}" y="{{ SCurve::TOP + 8 }}" style="fill:#c0392b;font-weight:600">Hoy</text>
    @endif

    <polyline points="{{ $curve->plannedPath() }}" fill="none" stroke="#0e4a1d" stroke-width="2" stroke-dasharray="6 5" />
    @foreach ($curve->plannedDots() as $d)
        <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="3.5" fill="#fff" stroke="#0e4a1d" stroke-width="1.5">
            <title>{{ month_short($d['p']['month']) }} · Proyectado {{ round($d['p']['planned']) }} %@isset($d['p']['planned_amount']) ({{ money($d['p']['planned_amount']) }})@endisset</title>
        </circle>
    @endforeach

    <polyline points="{{ $curve->executedPath() }}" fill="none" stroke="#137a26" stroke-width="2.5" stroke-linejoin="round" />
    @foreach ($curve->executedPoints() as $d)
        <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="3.5" fill="#137a26">
            <title>{{ month_short($d['p']['month']) }} · Ejecutado {{ round($d['p']['executed']) }} %@isset($d['p']['executed_amount']) ({{ money($d['p']['executed_amount']) }})@endisset</title>
        </circle>
    @endforeach
</svg>
