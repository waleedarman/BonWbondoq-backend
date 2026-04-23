<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const MANAGER = 'manager';
    public const ROASTING_EMPLOYEE = 'roasting_employee';
    public const INVENTORY_EMPLOYEE = 'inventory_employee';
    public const DISTRIBUTION_EMPLOYEE = 'distribution_employee';

    public const ALL = [
        self::MANAGER,
        self::ROASTING_EMPLOYEE,
        self::INVENTORY_EMPLOYEE,
        self::DISTRIBUTION_EMPLOYEE,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
