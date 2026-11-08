<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $guarded = [];

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function record(?Project $project, string $action, ?string $detail = null): self
    {
        return self::create([
            'project_id' => $project?->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'detail' => $detail,
        ]);
    }
}
