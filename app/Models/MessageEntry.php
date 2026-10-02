<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MessageEntry extends Model
{
    protected $fillable = [
        'message_conversation_id',
        'channel',
        'user_id',
        'direction',
        'sender',
        'recipient',
        'body',
        'message_type',
        'delivery_status',
        'external_message_id',
        'sent_at',
        'received_at',
        'delivered_at',
        'read_at',
        'technical_metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'received_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'technical_metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(MessageConversation::class, 'message_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityAttachments(): BelongsToMany
    {
        return $this->belongsToMany(
            ActivityAttachment::class,
            'message_entry_attachments',
            'message_entry_id',
            'activity_attachment_id'
        )->withTimestamps();
    }
}
