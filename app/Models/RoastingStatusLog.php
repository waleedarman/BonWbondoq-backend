<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoastingStatusLog extends Model
{
    protected $fillable = [
        'roasting_request_id',
        'changed_by',
        'from_status',
        'to_status',
        'notes',
    ];

    public function roastingRequest(): BelongsTo
    {
        return $this->belongsTo(RoastingRequest::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
