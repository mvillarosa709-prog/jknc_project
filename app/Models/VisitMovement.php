<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitMovement extends Model
{
    public const TYPE_CHECK_IN = 'check_in';

    public const TYPE_CHECK_OUT = 'check_out';

    protected $fillable = [
        'visit_id',
        'movement_type',
        'occurred_at',
        'branch_id',
        'latitude',
        'longitude',
        'accuracy_metres',
        'captured_at',
        'source',
        'location_label',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'captured_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_metres' => 'decimal:2',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
