<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailMailboxConnection extends Model
{
    protected $fillable = [
        'provider', 'email_address', 'google_account_id', 'encrypted_refresh_token',
        'access_token_expires_at', 'last_history_id', 'last_synced_at', 'last_error_at',
        'last_error_message', 'connection_status', 'user_id', 'connected_at', 'disconnected_at',
    ];

    protected $hidden = ['encrypted_refresh_token', 'sync_checkpoint'];

    protected function casts(): array
    {
        return [
            'sync_checkpoint' => 'array',
            'initial_sync_count' => 'integer',
            'access_token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $connection): void {
            if ($connection->connection_status === 'connected' && blank($connection->encrypted_refresh_token)) {
                throw new \InvalidArgumentException('A connected Gmail mailbox requires an encrypted refresh token.');
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->user();
    }

    public function emails(): HasMany
    {
        return $this->hasMany(EmailActivity::class, 'mailbox_connection_id');
    }
}
