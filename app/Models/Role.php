<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'name',
        'key',
        'description',
        'is_system',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
            ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withTimestamps();
    }
}
