<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RecordActivityLog extends Model
{
    use HasUlids;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'record_id',
        'record_intake_item_id',
        'record_file_id',
        'user_id',
        'action',
        'description',
        'properties',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Record activity logs are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Record activity logs are append-only and cannot be deleted.');
        });
    }

    public function intakeItem(): BelongsTo
    {
        return $this->belongsTo(RecordIntakeItem::class, 'record_intake_item_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(RecordFile::class, 'record_file_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'record_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * What this entry is about.
     *
     * Intake actions are recorded against the intake item, because no record
     * exists yet; everything else is recorded against the filed record. An entry
     * can end up with neither if its subject was removed, so this reads the
     * relation rather than assuming one is loaded.
     */
    public function subject(): ?Model
    {
        return $this->record ?? $this->intakeItem;
    }

    public function subjectType(): string
    {
        if ($this->record !== null) {
            return 'Record';
        }

        if ($this->intakeItem !== null) {
            return 'Intake Item';
        }

        return '—';
    }

    public function subjectLabel(): string
    {
        $subject = $this->subject();

        if ($subject === null) {
            return 'No subject';
        }

        return (string) ($subject->title ?? $subject->id);
    }
}
