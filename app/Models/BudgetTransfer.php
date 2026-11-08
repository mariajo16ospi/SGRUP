<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetTransfer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'submitted_at' => 'datetime'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function fromLine(): BelongsTo { return $this->belongsTo(BudgetLine::class, 'from_line_id'); }
    public function toLine(): BelongsTo { return $this->belongsTo(BudgetLine::class, 'to_line_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_id'); }
}
