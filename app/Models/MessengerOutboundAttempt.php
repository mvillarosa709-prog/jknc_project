<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessengerOutboundAttempt extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['attempted_at' => 'datetime', 'retry_at' => 'datetime'];
}
