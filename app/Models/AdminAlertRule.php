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
        'metric',
        'operator',
        'value',
        'period',
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
        'value' => 'decimal:2',
        'last_triggered_at' => 'datetime',
    ];
}
