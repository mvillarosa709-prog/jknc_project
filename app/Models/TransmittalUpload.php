<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransmittalUpload extends Model
{
    protected $table = 'transmittal_upload_sessions';

    protected $fillable = [
        'upload_id',
        'owner_session_hash',
        'upload_token_hash',
        'original_name',
        'mime_type',
        'expected_size',
        'chunk_size',
        'chunk_count',
        'chunk_hashes',
        'status',
        'storage_path',
        'sha256',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'chunk_hashes' => 'array',
            'expected_size' => 'integer',
            'chunk_size' => 'integer',
            'chunk_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
