@php
    use App\Support\ApprovalInbox;
    use App\Support\DemoData;

    $user = auth()->user();
    $menu = [];
    if ($user->role === 'vicerrectoria') {
        $inbox = app(ApprovalInbox::class)->pending();
        $purchaseCount = $inbox->where('type', 'compra')->count();
        $menu = [
            ['Panel general', 'panel', route('vic.panel'), request()->routeIs('vic.panel'), null],
            ['Proyectos y contratos', 'folder', route('vic.proyectos.index'), request()->routeIs('vic.proyectos.*'), null],
            ['Aprobaciones', 'inbox', route('vic.aprobaciones'), request()->routeIs('vic.aprobaciones*'), $inbox->count()],
            ['Entidades', 'bank', route('entidades.index'), request()->routeIs('entidades.*'), null],
            ['Cronograma', 'calendar', route('vic.cronograma'), request()->routeIs('vic.cronograma'), null],
            ['Seguimiento técnico', 'list', route('vic.seguimiento'), request()->routeIs('vic.seguimiento'), null],
            ['Compras', 'cart', route('vic.compras'), request()->routeIs('vic.compras*'), $purchaseCount],
            ['Reportes y experiencia', 'report', route('vic.reportes'), request()->routeIs('vic.reportes*'), null],
            ['Alertas', 'bell', route('vic.alertas'), request()->routeIs('vic.alertas'), DemoData::get()->alerts->where('resolved', false)->count()],
        ];
    }
    if ($user->role === 'investigador') {
        $mine = DemoData::get()->projects->where('coordinator_id', $user->id)->pluck('id')->all();
        $myPurchases = app(\App\Support\FinanceStore::class)->purchases()->filter(fn ($p) => in_array($p->project_id, $mine, true))
            ->whereIn('status', ['en_vicerrectoria', 'requiere_ajuste'])->count();
        $myAlerts = DemoData::get()->alerts->where('resolved', false)->filter(fn ($a) => in_array($a->project_id, $mine, true))->count();
        $menu = [
            ['Mis proyectos', 'folder', route('inv.home'), request()->routeIs('inv.home', 'inv.proyectos.*'), null],
            ['Cargar avance', 'upload', route('inv.cargar'), false, null],
            ['Mis compras', 'cart', route('inv.compras'), request()->routeIs('inv.compras*'), $myPurchases],
            ['Mis documentos', 'report', route('inv.documentos'), request()->routeIs('inv.documentos*'), null],
            ['Cronograma', 'calendar', route('inv.cronograma'), request()->routeIs('inv.cronograma'), null],
            ['Alertas', 'bell', route('inv.alertas'), request()->routeIs('inv.alertas'), $myAlerts],
        ];
    }
    if ($user->role === 'admin') {
        $menu = [
            ['Usuarios y roles', 'users', route('adm.usuarios'), request()->routeIs('adm.usuarios*'), null],
            ['Parámetros del sistema', 'sliders', route('adm.parametros'), request()->routeIs('adm.parametros*', 'adm.rubros*'), null],
            ['Auditoría', 'list', route('adm.auditoria'), request()->routeIs('adm.auditoria*'), null],
        ];
    }
    if ($user->role === 'financiero') {
        $fin = app(\App\Support\FinanceStore::class);
        $purchasesToManage = $fin->purchases()->filter(fn ($p) => $fin->needsCdp($p) || $fin->needsPayment($p))->count();
        $menu = [
            ['Control financiero', 'panel', route('fin.control'), request()->routeIs('fin.control*', 'fin.pago.*'), null],
            ['Validación de compras', 'cart', route('fin.compras'), request()->routeIs('fin.compras*'), $purchasesToManage],
            ['Control presupuestario', 'wallet', route('fin.presupuesto'), request()->routeIs('fin.presupuesto*', 'fin.proyecto*'), null],
            ['Soportes y pagos', 'folder', route('fin.documentos'), request()->routeIs('fin.documentos*'), null],
            ['Entidades', 'bank', route('entidades.index'), request()->routeIs('entidades.*'), null],
            ['Alertas', 'bell', route('fin.alertas'), request()->routeIs('fin.alertas'), $fin->alerts()->where('financial', true)->count()],
        ];
    }
    $menuTitle = [
        'vicerrectoria' => 'Menú · Vicerrectoría',
        'investigador' => 'Menú · Investigador',
        'financiero' => 'Menú · Área financiera',
        'admin' => 'Menú · Administración',
    ][$user->role] ?? 'Menú';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · SGRUP</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('img/icon-512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/sgrup.css') }}?v={{ filemtime(public_path('css/sgrup.css')) }}">
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-logo"><img src="{{ asset('img/logo-fet.png') }}" alt="Fundación Escuela Tecnológica de Neiva"></div>
            <div><div class="brand-name">SGRUP</div><div class="brand-sub">Gestión de<br>Proyectos</div></div>
        </div>
        <div class="menu-title">{{ $menuTitle }}</div>
        <nav class="menu" aria-label="Menú principal">
            @foreach ($menu as [$label, $icon, $url, $active, $count])
                <a href="{{ $url }}" @class(['active' => $active]) @if($active) aria-current="page" @endif>
                    <x-icon :name="$icon" />{{ $label }}
                    @if ($count)<span class="count">{{ $count }}</span>@endif
                </a>
            @endforeach
        </nav>
        <div class="promo"><b>#YoSoyFET</b><span>Investigación y extensión que transforman el Huila</span></div>
        <div class="who">
            <div class="who-row">
                <div class="avatar" aria-hidden="true">{{ $user->initials() }}</div>
                <div><div class="who-name">{{ $user->name }}</div><div class="who-role">{{ $user->roleLabel() }}</div></div>
            </div>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="logout" type="submit"><x-icon name="logout" size="14" />Cerrar sesión</button>
            </form>
        </div>
    </aside>
    <main class="main">
        @if (session('status'))
            <div class="flash ok" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash err" role="alert">{{ $errors->first() }}</div>
        @endif
        @yield('content')
    </main>
</div>
<script>
    // Cierra el panel de notificaciones al hacer clic fuera de él
    document.addEventListener('click', (e) => document.querySelectorAll('details.notif[open]').forEach((d) => { if (! d.contains(e.target)) d.removeAttribute('open'); }));
</script>
</body>
</html>
