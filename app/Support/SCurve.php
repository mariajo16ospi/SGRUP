<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Construye los datos de la Curva S (acumulado proyectado vs. ejecutado) y
 * las coordenadas SVG para dibujarla.
 */
class SCurve
{
    public const W = 720;
    public const H = 300;
    public const LEFT = 44;
    public const RIGHT = 12;
    public const TOP = 14;
    public const BOTTOM = 34;

    /**
     * @param  array<int, array{month: Carbon, planned: float, executed: ?float}>  $points  porcentajes 0–100
     */
    public function __construct(public array $points, public ?Carbon $today = null)
    {
        $this->today ??= today();
    }

    public static function forProject(Project $project): self
    {
        $value = max(1, (float) $project->value);
        $points = $project->curvePoints->map(fn ($p) => [
            'month' => $p->month,
            'planned' => $p->planned_cumulative / $value * 100,
            'executed' => $p->executed_cumulative === null ? null : $p->executed_cumulative / $value * 100,
            'planned_amount' => (float) $p->planned_cumulative,
            'executed_amount' => $p->executed_cumulative === null ? null : (float) $p->executed_cumulative,
        ])->all();

        return new self($points);
    }

    /** Curva consolidada de varios proyectos (porcentaje del presupuesto total). */
    public static function consolidated(Collection $projects): self
    {
        $total = max(1, (float) $projects->sum('value'));
        $first = $projects->min('start_date')->copy()->startOfMonth();
        $last = $projects->max('end_date')->copy()->startOfMonth();

        $points = [];
        for ($m = $first->copy(); $m <= $last; $m->addMonth()) {
            $planned = 0;
            $executed = 0;
            $anyExecuted = false;
            $allReported = true;
            foreach ($projects as $project) {
                $byMonth = $project->curvePoints->keyBy(fn ($p) => $p->month->format('Y-m'));
                $pts = $project->curvePoints;
                if ($m < $pts->first()->month) {
                    continue;
                }
                $point = $byMonth[$m->format('Y-m')] ?? null;
                $planned += $point ? $point->planned_cumulative : $pts->last()->planned_cumulative;

                $lastExecuted = $pts->whereNotNull('executed_cumulative')->last();
                if ($point && $point->executed_cumulative !== null) {
                    $executed += $point->executed_cumulative;
                    $anyExecuted = true;
                } elseif ($lastExecuted && $m > $lastExecuted->month && $lastExecuted->month->eq($pts->last()->month)) {
                    $executed += $lastExecuted->executed_cumulative;   // proyecto ya terminado
                } else {
                    $allReported = false;
                    if ($lastExecuted && $m > $lastExecuted->month) {
                        $executed += $lastExecuted->executed_cumulative;
                    }
                }
            }
            $points[] = [
                'month' => $m->copy(),
                'planned' => $planned / $total * 100,
                'executed' => $anyExecuted && $allReported ? $executed / $total * 100 : null,
            ];
        }

        return new self($points);
    }

    public function x(int $i): float
    {
        $n = max(1, count($this->points) - 1);

        return self::LEFT + $i * (self::W - self::LEFT - self::RIGHT) / $n;
    }

    public function y(float $pct): float
    {
        return self::TOP + (100 - $pct) / 100 * (self::H - self::TOP - self::BOTTOM);
    }

    public function plannedPath(): string
    {
        return collect($this->points)->map(fn ($p, $i) => round($this->x($i), 1) . ',' . round($this->y($p['planned']), 1))->implode(' ');
    }

    public function executedPoints(): array
    {
        return collect($this->points)->filter(fn ($p) => $p['executed'] !== null)
            ->map(fn ($p, $i) => ['x' => round($this->x($i), 1), 'y' => round($this->y($p['executed']), 1), 'p' => $p])
            ->values()->all();
    }

    public function executedPath(): string
    {
        return collect($this->executedPoints())->map(fn ($p) => $p['x'] . ',' . $p['y'])->implode(' ');
    }

    /** Polígono del área bajo la curva ejecutada. */
    public function executedArea(): string
    {
        $pts = $this->executedPoints();
        if (! $pts) {
            return '';
        }
        $base = round($this->y(0), 1);

        return $pts[0]['x'] . ',' . $base . ' ' . $this->executedPath() . ' ' . end($pts)['x'] . ',' . $base;
    }

    public function plannedDots(): array
    {
        return collect($this->points)->map(fn ($p, $i) => ['x' => round($this->x($i), 1), 'y' => round($this->y($p['planned']), 1), 'p' => $p])->all();
    }

    /** Posición x de la línea "Hoy", interpolada dentro del mes. */
    public function todayX(): ?float
    {
        foreach ($this->points as $i => $p) {
            if ($p['month']->format('Y-m') === $this->today->format('Y-m')) {
                return $this->x($i) + ($this->today->day - 1) / $this->today->daysInMonth * ($this->x(min($i + 1, count($this->points) - 1)) - $this->x($i));
            }
        }

        return null;
    }

    /** Etiquetas del eje X (si hay muchos meses se muestra uno de cada dos). */
    public function xLabels(): array
    {
        $step = count($this->points) > 14 ? 2 : 1;
        $last = count($this->points) - 1;

        return collect($this->points)->map(fn ($p, $i) => ['x' => round($this->x($i), 1), 'label' => month_short($p['month'])])
            ->filter(fn ($l, $i) => $i % $step === 0 || $i === $last)->values()->all();
    }

    /** Último punto con ejecución reportada. */
    public function lastExecuted(): ?array
    {
        return collect($this->points)->filter(fn ($p) => $p['executed'] !== null)->last();
    }
}
