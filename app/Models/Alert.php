<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['requires_action' => 'boolean', 'resolved' => 'boolean'];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    public function scopeOpen($q)
    {
        return $q->where('resolved', false);
    }
}
