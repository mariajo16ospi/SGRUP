<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Panel de notificaciones de cada rol (mockups «M · Notificaciones»).
 *
 * Se arman a partir de lo que ya pasa en la demo: solicitudes por decidir,
 * cambios de estado de las compras, alertas y auditoría. «Marcar como leídas»
 * guarda en DemoState los ids vistos por cada usuario.
 */
class Notifications
{
    /** Cuántas se muestran en el panel. */
    public const LIMIT = 5;

    public function __construct(private ApprovalInbox $inbox, private FinanceStore $finance, private ProjectTracking $tracking, private AdminStore $adminStore) {}

    /**
     * @return Collection<int, array{id: string, tone: string, title: string, detail: string, at: ?Carbon, url: ?string, unread: bool}>
     */
    public function for(User $user): Collection
    {
        $items = match ($user->role) {
            'vicerrectoria' => $this->vicerrectoria(),
            'investigador' => $this->investigador($user),
            'financiero' => $this->financiero(),
            'admin' => $this->admin(),
            default => collect(),
        };
        $read = DemoState::get("notif_read.{$user->id}", []);

        return $items
            ->sortByDesc(fn ($n) => $n['at']?->timestamp ?? 0)
            ->take(self::LIMIT)
            ->map(fn ($n) => $n + ['unread' => ! in_array($n['id'], $read, true)])
            ->values();
    }

    public function markRead(User $user): void
    {
        $read = DemoState::get("notif_read.{$user->id}", []);
        DemoState::put("notif_read.{$user->id}", array_values(array_unique(array_merge($read, $this->for($user)->pluck('id')->all()))));
    }

    /** Enlace «Ver todas las alertas» del panel. */
    public static function allUrl(User $user): string
    {
        return match ($user->role) {
            'vicerrectoria' => route('vic.alertas'),
            'investigador' => route('inv.alertas'),
            'financiero' => route('fin.alertas'),
            default => route('adm.auditoria'),
        };
    }

    private function vicerrectoria(): Collection
    {
        $items = $this->inbox->pending()->map(fn ($i) => match ($i['type']) {
            'compra' => $this->item("compra-{$i['id']}", $i['flag'] ? 'red' : 'amber',
                ($i['flag'] && str_starts_with($i['flag'], 'Excede') ? 'Sobrecosto en ' : 'Compra por aprobar · ') . strtok($i['title'], ' '),
                $i['subtitle'] ?? $i['title'], $i['received'], $i['review_url']),
            'avance' => $this->item("avance-{$i['id']}", 'amber', 'Nuevo avance por revisar',
                "{$i['project']->name} · " . mb_strtolower(substr($i['title'], 10)), $i['received'], $i['review_url']),
            'traslado' => $this->item("traslado-{$i['id']}", 'blue', 'Traslado ' . strtok($i['title'], ' ') . ' solicitado',
                trim(substr($i['title'], strpos($i['title'], '·') + 2)) . ' · ' . money($i['value']), $i['received'], $i['review_url']),
            default => $this->item("proyecto-{$i['id']}", 'blue', 'Proyecto nuevo propuesto', "{$i['title']} · {$i['requester']}", $i['received'], $i['review_url']),
        });

        return $items->concat($this->alerts(fn ($a) => $a->requires_action, route('vic.alertas')));
    }

    private function investigador(User $user): Collection
    {
        $mine = DemoData::get()->projects->where('coordinator_id', $user->id);
        $ids = $mine->pluck('id')->all();
        $items = collect();

        // Respuestas de Vicerrectoría y Financiera a mis solicitudes de compra
        foreach ($this->finance->purchases()->filter(fn ($p) => in_array($p->project_id, $ids, true)) as $p) {
            $log = collect($p->changes_log)->last();
            [$tone, $title] = match ($p->status) {
                'requiere_ajuste' => ['amber', "{$p->code} devuelta para ajuste"],
                'rechazada' => ['red', "{$p->code} rechazada"],
                'en_financiera' => ['green', $p->cdp ? "{$p->code}: CDP expedido" : "{$p->code} aprobada"],
                'aprobada' => ['green', "{$p->code} aprobada"],
                'pagada' => ['green', "{$p->code} pagada"],
                default => [null, null],
            };
            if ($title && ($log || $p->status === 'requiere_ajuste')) {
                $items->push($this->item("compra-{$p->code}-{$p->status}", $tone, $title,
                    $log['text'] ?? "{$p->description} · " . money($p->value),
                    $log ? Carbon::parse($log['at']) : $p->submitted_at, route('inv.compras', ['ver' => $p->code])));
            }
        }

        // Recordatorio del próximo avance mensual
        foreach ($mine->where('status', 'activo') as $p) {
            if ($next = $this->tracking->nextReport($p)) {
                $items->push($this->item("avance-{$p->id}-{$next['period']->format('Y-m')}", 'blue', 'Recordatorio de avance',
                    'El avance de ' . mb_strtolower(month_long($next['period'])) . " de {$p->name} vence el " . date_es($next['due']),
                    $next['due']->copy()->subDays(5), route('inv.proyectos.avances', [$p, 'cargar' => 1])));
            }
        }

        return $items->concat($this->alerts(fn ($a) => in_array($a->project_id, $ids, true) && $a->severity === 'critica', route('inv.alertas')));
    }

    private function financiero(): Collection
    {
        $items = collect();
        foreach ($this->finance->purchases() as $p) {
            $log = collect($p->changes_log)->last();
            $at = $log ? Carbon::parse($log['at']) : $p->submitted_at;
            if ($this->finance->needsCdp($p)) {
                $items->push($this->item("cdp-{$p->code}", 'amber', 'CDP por expedir', "{$p->code} aprobada por Vicerrectoría · " . money($p->value), $at,
                    route('fin.compras', ['ver' => $p->code])));
            } elseif ($this->finance->needsPayment($p)) {
                $items->push($this->item("pago-{$p->code}", 'blue', 'Pago por registrar', "{$p->code} · {$p->description}", $at, route('fin.pago.form', $p->code)));
            }
        }

        return $items->concat($this->alerts(fn ($a) => $a->financial, route('fin.alertas')));
    }

    private function admin(): Collection
    {
        return $this->adminStore->audit()
            ->reject(fn ($r) => $r['action'] === 'Inició sesión')
            ->take(self::LIMIT)
            ->map(fn ($r, $i) => $this->item('audit-' . md5($r['at']->toDateTimeString() . $r['action'] . $r['detail']),
                ['green' => 'green', 'red' => 'red', 'amber' => 'amber'][$r['tone']] ?? 'blue',
                "{$r['action']} · {$r['user']}", $r['detail'], $r['at'], route('adm.auditoria')));
    }

    /** Alertas activas que cumplen el filtro (sin fecha: van al final del panel). */
    private function alerts(callable $filter, string $url): Collection
    {
        return DemoData::get()->alerts->where('resolved', false)->filter($filter)
            ->map(fn ($a) => $this->item("alerta-{$a->id}", $a->severity === 'critica' ? 'red' : ($a->severity === 'advertencia' ? 'amber' : 'blue'),
                $a->title, $a->detail, null, $url));
    }

    private function item(string $id, string $tone, string $title, string $detail, ?Carbon $at, ?string $url): array
    {
        return compact('id', 'tone', 'title', 'detail', 'at', 'url');
    }
}
