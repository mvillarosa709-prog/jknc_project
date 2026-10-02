<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class Visit extends Model
{
    public const STATUS_CHECKED_IN = 'Checked In';

    public const STATUS_COMPLETED = 'Completed';

    protected $fillable = [
        'visit_reference',
        'visitor_id',
        'status',
        'checked_in_at',
        'checked_out_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Visit $visit): void {
            if (blank($visit->visit_reference)) {
                $visit->visit_reference = static::generateNextReference();
            }
        });
    }

    public static function generateNextReference(?string $date = null): string
    {
        $date ??= now()->format('Ymd');
        $prefix = "VST-{$date}-";

        $nextNumber = DB::transaction(function () use ($prefix): int {
            $latestReference = static::query()
                ->where('visit_reference', 'like', $prefix.'%')
                ->orderByDesc('visit_reference')
                ->lockForUpdate()
                ->value('visit_reference');

            if (! is_string($latestReference) || preg_match('/^VST-\d{8}-\d{4}$/', $latestReference) !== 1) {
                return 1;
            }

            return ((int) substr($latestReference, -4)) + 1;
        });

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(VisitMovement::class);
    }

    public function activity(): MorphTo
    {
        return $this->morphTo();
    }
}
