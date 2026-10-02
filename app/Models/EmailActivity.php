<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class EmailActivity extends Model
{
    protected $fillable = [
        'provider',
        'mailbox_key',
        'provider_message_id',
        'thread_id',
        'rfc_message_id',
        'in_reply_to',
        'references_header',
        'mailbox_owner_user_id',
        'mailbox_connection_id',
        'company_id',
        'contact_id',
        'prepared_by_user_id',
        'subject',
        'sender_email',
        'recipients',
        'cc_recipients',
        'bcc_recipients',
        'direction',
        'occurred_at',
        'summary',
        'body',
        'handling',
        'has_attachments',
        'attachment_metadata',
        'technical_metadata',
        'client_report_description',
        'include_in_client_report',
        'include_body_in_client_report',
        'delivery_status',
        'sent_at',
        'provider_error',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'recipients' => 'array',
            'cc_recipients' => 'array',
            'bcc_recipients' => 'array',
            'occurred_at' => 'datetime',
            'sent_at' => 'datetime',
            'has_attachments' => 'boolean',
            'attachment_metadata' => 'array',
            'technical_metadata' => 'array',
            'include_in_client_report' => 'boolean',
            'include_body_in_client_report' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (EmailActivity $email): void {
            if ($email->isDirty(['provider', 'mailbox_key']) || ($email->isDirty('provider_message_id') && $email->getOriginal('delivery_status') !== 'Pending')) {
                throw new LogicException('An email provider identity is immutable.');
            }
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('mailbox_owner_user_id', $user->getKey());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contactRecord(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function mailboxOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mailbox_owner_user_id');
    }

    public function mailboxConnection(): BelongsTo
    {
        return $this->belongsTo(EmailMailboxConnection::class, 'mailbox_connection_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ActivityAttachment::class, 'activity_id')
            ->where('activity_type', 'EMAIL');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }
}
