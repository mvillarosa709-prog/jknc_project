<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityAuditLog extends Model
{
    protected $fillable = [
        'activity_type',
        'activity_id',
        'action',
        'user_id',
        'changes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
