<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    public const TYPE_IN = 'in';
    public const TYPE_OUT = 'out';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_IN,
        self::TYPE_OUT,
        self::TYPE_ADJUSTMENT,
    ];

    public const REASON_SUPPLY = 'supply';
    public const REASON_ROASTING_USAGE = 'roasting_usage';
    public const REASON_ROASTING_OUTPUT = 'roasting_output';
    public const REASON_SHIPMENT = 'shipment';
    public const REASON_MANUAL_ADJUSTMENT = 'manual_adjustment';
    public const REASON_RETURN = 'return';

    public const REASONS = [
        self::REASON_SUPPLY,
        self::REASON_ROASTING_USAGE,
        self::REASON_ROASTING_OUTPUT,
        self::REASON_SHIPMENT,
        self::REASON_MANUAL_ADJUSTMENT,
        self::REASON_RETURN,
    ];

    protected $fillable = [
        'product_id',
        'branch_id',
        'performed_by',
        'movement_type',
        'reason',
        'quantity',
        'reference_type',
        'reference_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
