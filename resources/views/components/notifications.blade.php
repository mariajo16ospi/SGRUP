{{-- Campana con el panel de notificaciones del rol (mockups «M · Notificaciones»). --}}
@php
    use App\Support\Notifications;
    $user = auth()->user();
    $items = app(Notifications::class)->for($user);
    $unread = $items->where('unread', true)->count();
    $when = function ($at) {
        if (! $at) {
            return 'Activa';
        }
        if ($at->isToday()) {
            return $at->diffInHours(now()) < 1 ? 'Hace un momento' : 'Hoy ' . $at->format('G:i');
        }

        return $at->isYesterday() ? 'Ayer' : date_es($at);
    };
@endphp
<details class="notif">
    <summary class="btn btn-icon" aria-label="Notificaciones{{ $unread ? " ({$unread} sin leer)" : '' }}">
        <x-icon name="bell" />@if ($unread)<span class="dot"></span>@endif
    </summary>
    <div class="notif-panel" role="dialog" aria-label="Notificaciones">
        <div class="notif-head">
            <h2>Notificaciones</h2>
            @if ($unread)
                <form method="post" action="{{ route('notificaciones.leidas') }}">
                    @csrf
                    <button class="link" type="submit">Marcar como leídas</button>
                </form>
            @endif
        </div>
        @forelse ($items as $n)
            <a class="notif-item {{ $n['unread'] ? 'unread' : '' }}" href="{{ $n['url'] }}">
                <i class="notif-dot {{ $n['tone'] }}" aria-hidden="true"></i>
                <span class="notif-text"><b>{{ $n['title'] }}</b><span>{{ $n['detail'] }}</span></span>
                <span class="notif-when">{{ $when($n['at']) }}</span>
            </a>
        @empty
            <p class="notif-empty">No tienes notificaciones.</p>
        @endforelse
        <a class="notif-foot" href="{{ Notifications::allUrl($user) }}">{{ $user->role === 'admin' ? 'Ver toda la auditoría' : 'Ver todas las alertas' }} →</a>
    </div>
</details>
