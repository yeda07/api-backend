<?php

namespace App\Models;

use App\Models\Traits\HasPublicUid;
use App\Models\Traits\TenantScope;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasPublicUid, TenantScope;

    protected $fillable = [
        'uid',
        'tenant_id',
        'expense_category_id',
        'supplier_id',
        'owner_user_id',
        'expenseable_type',
        'expenseable_id',
        'cost_center_id',
        'cost_center',
        'expense_number',
        'title',
        'description',
        'amount',
        'currency',
        'expense_date',
        'status',
        'meta',
    ];

    protected $hidden = [
        'id',
        'tenant_id',
        'expense_category_id',
        'supplier_id',
        'owner_user_id',
        'expenseable_id',
        'cost_center_id',
    ];

    protected $appends = [
        'expense_category_uid',
        'supplier_uid',
        'owner_user_uid',
        'expenseable_uid',
        'cost_center_uid',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'meta' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function expenseable()
    {
        return $this->morphTo();
    }

    public function getExpenseCategoryUidAttribute(): ?string
    {
        return $this->category?->uid
            ?? ($this->expense_category_id ? ExpenseCategory::query()->whereKey($this->expense_category_id)->value('uid') : null);
    }

    public function getSupplierUidAttribute(): ?string
    {
        return $this->supplier?->uid
            ?? ($this->supplier_id ? Supplier::query()->whereKey($this->supplier_id)->value('uid') : null);
    }

    public function getOwnerUserUidAttribute(): ?string
    {
        return $this->owner?->uid
            ?? ($this->owner_user_id ? User::query()->whereKey($this->owner_user_id)->value('uid') : null);
    }

    public function getExpenseableUidAttribute(): ?string
    {
        return $this->expenseable?->uid;
    }

    public function getCostCenterUidAttribute(): ?string
    {
        return $this->costCenter?->uid
            ?? ($this->cost_center_id ? CostCenter::query()->whereKey($this->cost_center_id)->value('uid') : null);
    }
}
