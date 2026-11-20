<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordClassification extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function indexes()
    {
        return $this->hasMany(RecordIndex::class);
    }

    /**
     * The plain text this Classification's whole block is searched against.
     *
     * A search is answered against this rather than against the row on its own, so
     * that an Index Code hit still leaves the Classification it belongs to on
     * screen instead of orphaning the match. Lives here rather than in a
     * controller because both the Classifications page and the setup modal show
     * this same tree and must search it identically.
     *
     * Requires the indexes relation to be loaded; a classification fetched without
     * it searches on its own fields only.
     */
    public function searchText(): string
    {
        return trim(implode(' ', array_filter([
            $this->code,
            $this->name,
            (string) $this->description,
            ...$this->indexes->map(fn (RecordIndex $index): string => implode(' ', [
                $index->code,
                $index->name,
                (string) $index->description,
            ])),
        ], fn (string $value): bool => $value !== '')));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
