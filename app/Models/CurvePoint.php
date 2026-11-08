<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurvePoint extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'planned_cumulative' => 'decimal:2',
            'executed_cumulative' => 'decimal:2',
        ];
    }
}
