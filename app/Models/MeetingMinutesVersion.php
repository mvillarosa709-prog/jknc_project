<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingMinutesVersion extends Model
{
    protected $fillable = [
        'meeting_id',
        'version_number',
        'content',
        'status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
