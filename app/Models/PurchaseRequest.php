<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequest extends Model
{
    protected $guarded = [];

    public const STATUSES = [
        'en_vicerrectoria' => 'En Vicerrectoría',
        'en_financiera' => 'En área financiera',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'devuelta' => 'Devuelta',
        'requiere_ajuste' => 'Requiere ajuste',
        'pagada' => 'Pagada',
        'retirada' => 'Retirada',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'original_value' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function budgetLine(): BelongsTo { return $this->belongsTo(BudgetLine::class); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'en_vicerrectoria', 'requiere_ajuste', 'devuelta' => 'amber',
            'en_financiera' => 'blue',
            'aprobada', 'pagada' => 'green',
            'retirada' => 'gray',
            default => 'red',
        };
    }

    /** Porcentaje en que el valor actual supera el valor inicialmente cotizado. */
    public function overrunPercent(): ?int
    {
        if (! $this->original_value || $this->value <= $this->original_value) {
            return null;
        }

        return (int) round(($this->value - $this->original_value) / $this->original_value * 100);
    }
}
