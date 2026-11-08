@props(['alert', 'href' => null])
@php
    $icon = [
        'sobrecosto' => 'trend', 'presupuesto' => 'wallet', 'rubro' => 'warning', 'entrega_vencida' => 'box',
        'rezago' => 'hourglass', 'ejecucion' => 'money', 'plazo' => 'calendar', 'vencimiento' => 'hourglass', 'reporte' => 'report',
    ][$alert->kind] ?? 'warning';
@endphp
<div @class(['alert', 'warn' => $alert->severity === 'advertencia', 'info' => $alert->severity === 'informativa'])>
    <x-icon :name="$icon" size="16" />
    <div class="alert-body">
        <div class="alert-title">{{ $alert->title }}</div>
        <div class="alert-detail">{{ $alert->detail }}</div>
    </div>
    @if ($href)<a href="{{ $href }}">Ver<span class="sr-only"> {{ $alert->title }}</span></a>@endif
</div>
