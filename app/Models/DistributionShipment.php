<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DistributionShipment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_READY_FOR_PICKUP,
        self::STATUS_TRANSFERRED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'product_id',
        'branch_id',
        'created_by',
        'assigned_to',
        'shipment_code',
        'status',
        'quantity',
        'destination',
        'recipient_name',
        'notes',
        'prepared_at',
        'transferred_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'prepared_at' => 'datetime',
            'transferred_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
