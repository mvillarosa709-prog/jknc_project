<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Visitor extends Model
{
    protected $fillable = [
        'visitor_reference',
        'name',
        'mobile',
        'email',
        'company_name',
        'contact_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Visitor $visitor): void {
            if (blank($visitor->visitor_reference)) {
                $visitor->visitor_reference = static::generateNextReference();
            }
        });
    }

    public static function generateNextReference(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = "VIS-{$year}-";

        $nextNumber = DB::transaction(function () use ($prefix): int {
            $latestReference = static::query()
                ->where('visitor_reference', 'like', $prefix.'%')
                ->orderByDesc('visitor_reference')
                ->lockForUpdate()
                ->value('visitor_reference');

            if (! is_string($latestReference) || preg_match('/^VIS-\d{4}-\d{6}$/', $latestReference) !== 1) {
                return 1;
            }

            return ((int) substr($latestReference, -6)) + 1;
        });

        return $prefix.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function activeVisit(): HasMany
    {
        return $this->visits()->where('status', Visit::STATUS_CHECKED_IN);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = '%'.addcslashes($search, '%_\\').'%';

        return $query->where(function (Builder $searchQuery) use ($term): void {
            $searchQuery
                ->where('visitor_reference', 'like', $term)
                ->orWhere('name', 'like', $term)
                ->orWhere('mobile', 'like', $term)
                ->orWhere('email', 'like', $term);
        });
    }
}
