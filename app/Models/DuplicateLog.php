<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'level',
        'message',
        'context'
    ];

    protected $casts = [
        'context' => 'array'
    ];
}
