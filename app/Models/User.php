<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Traits\HasAccessControl;
use App\Models\Traits\HasAdminAccessControl;
use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;

class User extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable, HasAccessControl, HasAdminAccessControl, HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'name',
        'email',
        'password',
        'tenant_id',
        'is_platform_admin',
        'manager_id',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_recovery_codes',
        'avatar_url',
    ];

    protected $hidden = [
        'id',
        'password',
        'tenant_id',
        'is_platform_admin',
        'manager_id',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $appends = [
        'tenant_uid',
        'manager_uid',
        'role_uid',
        'role_name',
        'is_active',
        'status',
    ];

    protected $casts = [
        'is_platform_admin' => 'boolean',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'two_factor_recovery_codes' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function projectAssignments()
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function managedTeams()
    {
        return $this->hasMany(Team::class, 'manager_user_id');
    }

    public function getTenantUidAttribute()
    {
        return $this->tenant?->uid;
    }

    public function getManagerUidAttribute()
    {
        return $this->manager?->uid;
    }

    public function getRoleUidAttribute()
    {
        return $this->roles->first()?->uid;
    }

    public function getRoleNameAttribute()
    {
        return $this->roles->first()?->name;
    }

    public function getIsActiveAttribute(): bool
    {
        return !$this->isLocked();
    }

    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'ACTIVO' : 'INACTIVO';
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && now()->lt($this->locked_until);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    /*
    |--------------------------------------------------------------------------
    |  acceso rápido al plan del tenant
    |--------------------------------------------------------------------------
    */
    public function plan()
    {
        return $this->tenant?->plan();
    }

    /*
    |--------------------------------------------------------------------------
    |  validar límite de usuarios del plan
    |--------------------------------------------------------------------------
    */
    public static function canCreateUser($tenant)
    {
        $plan = $tenant->plan;

        // Si no hay plan → permitir (modo flexible)
        if (!$plan) {
            return true;
        }

        // Si es ilimitado → permitir
        if (is_null($plan->max_users)) {
            return true;
        }

        // Validar cantidad actual
        return $tenant->users()->count() < $plan->max_users;
    }
}
