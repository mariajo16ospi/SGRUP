<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Project extends Model
{
    protected $guarded = [];

    public const TYPES = ['investigacion' => 'Investigación', 'extension' => 'Extensión'];

    public const MODALITIES = [
        'convenio' => 'Convenio',
        'contrato' => 'Contrato',
        'recursos_propios' => 'Recursos propios',
        'convocatoria' => 'Convocatoria',
    ];

    public const PHASES = ['preinversion' => 'Preinversión', 'inversion' => 'Inversión', 'operacion' => 'Operación'];

    public const HEALTH = ['en_tiempo' => 'En tiempo', 'sobrecosto' => 'Sobrecosto', 'retraso' => 'Retraso'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'preinvestment_end' => 'date',
            'investment_end' => 'date',
            'value' => 'decimal:2',
            'executed_amount' => 'decimal:2',
        ];
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    public function curvePoints(): HasMany
    {
        return $this->hasMany(CurvePoint::class)->orderBy('month');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'activo');
    }

    public function typeLabel(): string { return self::TYPES[$this->type] ?? $this->type; }
    public function modalityLabel(): string { return self::MODALITIES[$this->modality] ?? $this->modality; }
    public function phaseLabel(): string { return self::PHASES[$this->phase] ?? $this->phase; }

    /** Etiqueta de estado que se muestra en tarjetas y tablas. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'liquidado' => 'Liquidado',
            'propuesto' => 'Propuesto',
            default => self::HEALTH[$this->health] ?? $this->health,
        };
    }

    public function statusTone(): string
    {
        return match (true) {
            $this->status !== 'activo' => 'gray',
            $this->health === 'sobrecosto' => 'red',
            $this->health === 'retraso' => 'amber',
            default => 'green',
        };
    }

    public function financialProgress(): int
    {
        return $this->value > 0 ? (int) round($this->executed_amount / $this->value * 100) : 0;
    }

    public function contractLabel(): string
    {
        return match ($this->modality) {
            'convenio' => 'Convenio',
            'contrato' => 'Contrato',
            default => $this->modalityLabel(),
        };
    }

    public function timeElapsedPercent(?Carbon $today = null): int
    {
        $today ??= today();
        $total = $this->start_date->diffInDays($this->end_date);
        if ($total <= 0) {
            return 100;
        }
        $elapsed = $this->start_date->diffInDays(min($today, $this->end_date), false);

        return (int) max(0, min(100, round($elapsed / $total * 100)));
    }

    public function daysRemaining(?Carbon $today = null): int
    {
        return (int) max(0, ($today ?? today())->diffInDays($this->end_date, false));
    }

    /** Las tres fases de la ruta de ejecución con sus fechas y estado. */
    public function phaseRoute(): array
    {
        $order = array_keys(self::PHASES);
        $current = array_search($this->phase, $order);
        $ranges = [
            'preinversion' => [$this->start_date, $this->preinvestment_end],
            'inversion' => [$this->preinvestment_end?->copy()->addDay(), $this->investment_end],
            'operacion' => [$this->investment_end?->copy()->addDay(), $this->end_date],
        ];

        return collect($order)->map(function ($key, $i) use ($current, $ranges) {
            [$from, $to] = $ranges[$key];
            $state = $this->status === 'liquidado' || $i < $current ? 'done' : ($i === $current ? 'current' : 'next');
            $progress = null;
            if ($state === 'current' && $from && $to) {
                $span = max(1, $from->diffInDays($to));
                $progress = (int) max(0, min(100, round($from->diffInDays(min(today(), $to), false) / $span * 100)));
            }

            return [
                'number' => $i + 1,
                'key' => $key,
                'label' => self::PHASES[$key],
                'state' => $state,
                'from' => $from,
                'to' => $to,
                'progress' => $progress,
            ];
        })->all();
    }

    public function nextPhase(): ?string
    {
        $order = array_keys(self::PHASES);
        $i = array_search($this->phase, $order);

        return $order[$i + 1] ?? null;
    }
}
