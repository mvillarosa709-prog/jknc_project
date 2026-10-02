<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageConversation extends Model
{
    protected $fillable = [
        'company_id',
        'contact_id',
        'assigned_user_id',
        'conversation_title',
        'conversation_summary',
        'channel',
        'direction',
        'participant_contact_ids',
        'started_at',
        'ended_at',
        'duration_minutes',
        'line_account',
        'handling',
        'is_linked',
        'last_message_at',
        'external_conversation_id',
        'technical_metadata',
        'client_report_description',
        'include_in_client_report',
    ];

    protected $casts = [
        'is_linked' => 'boolean',
        'last_message_at' => 'datetime',
        'technical_metadata' => 'array',
        'participant_contact_ids' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_minutes' => 'integer',
        'include_in_client_report' => 'boolean',
    ];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->where('assigned_user_id', $user->getKey())
                ->orWhere('prepared_by_user_id', $user->getKey());
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MessageEntry::class);
    }

    public function latestEntry()
    {
        return $this->hasOne(MessageEntry::class)->latestOfMany();
    }
}
