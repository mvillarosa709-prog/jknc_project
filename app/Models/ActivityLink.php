<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLink extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'related_type',
        'related_id',
        'created_by_user_id',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
