<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use Illuminate\Database\Eloquent\Model;

class AdminRole extends Model
{
    use HasPublicUid;

    protected $fillable = [
        'uid',
        'name',
        'key',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    protected $hidden = [
        'id',
    ];

    protected $appends = [
        'total_users',
    ];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'admin_role_permission');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'admin_role_user');
    }

    public function getTotalUsersAttribute(): int
    {
        return (int) ($this->attributes['users_count'] ?? 0);
    }
}
