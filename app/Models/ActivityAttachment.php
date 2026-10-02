<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\Model;

class ActivityAttachment extends Model
{
    protected $fillable = [
        'activity_type',
        'activity_id',
        'filename',
        'original_filename',
        'storage_disk',
        'storage_path',
        'external_url',
        'mime_type',
        'size_bytes',
        'category',
        'uploaded_by_user_id',
        'uploaded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    public function messageEntries(): BelongsToMany
    {
        return $this->belongsToMany(
            MessageEntry::class,
            'message_entry_attachments',
            'activity_attachment_id',
            'message_entry_id'
        )->withTimestamps();
    }
}
