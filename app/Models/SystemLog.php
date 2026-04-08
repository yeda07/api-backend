<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    use HasPublicUid;

    protected $fillable = [
        'uid',
        'tenant_id',
        'level',
        'message',
        'context',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
