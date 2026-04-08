<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasPublicUid;

    protected $fillable = [
        'uid',
        'key',
        'module',
        'action',
        'description',
    ];

    protected $hidden = [
        'id',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'permission_user')
            ->withTimestamps();
    }
}
