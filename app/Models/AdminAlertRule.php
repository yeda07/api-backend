<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use Illuminate\Database\Eloquent\Model;

class AdminAlertRule extends Model
{
    use HasPublicUid;

    protected $fillable = [
        'uid',
        'name',
        'condition_text',
        'channels',
        'is_active',
        'last_triggered_at',
    ];

    protected $hidden = [
        'id',
    ];

    protected $casts = [
        'channels' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];
}
