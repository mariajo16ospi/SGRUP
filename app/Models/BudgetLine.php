<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['budgeted' => 'decimal:2', 'executed' => 'decimal:2', 'committed' => 'decimal:2'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Saldo disponible del rubro: presupuesto − ejecutado − comprometido. */
    public function available(): float
    {
        return (float) $this->budgeted - (float) $this->executed - (float) $this->committed;
    }
}
