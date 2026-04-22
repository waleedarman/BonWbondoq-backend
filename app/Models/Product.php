<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'sku',
        'description',
        'unit',
        'quantity',
        'minimum_quantity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'minimum_quantity' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function roastingRequests(): HasMany
    {
        return $this->hasMany(RoastingRequest::class);
    }

    public function distributionShipments(): HasMany
    {
        return $this->hasMany(DistributionShipment::class);
    }
}
