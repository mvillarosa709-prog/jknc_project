<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessagingWebhookEvent extends Model
{
    protected $guarded = [];
    protected $hidden = ['payload'];
    protected $casts = ['payload' => 'encrypted:array', 'processed_at' => 'datetime'];
}
