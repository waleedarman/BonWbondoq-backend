<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'branch_id',
        'approved_by',
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'approved_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employeeRequest(): HasOne
    {
        return $this->hasOne(EmployeeRequest::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'approved_by');
    }

    public function reviewedEmployeeRequests(): HasMany
    {
        return $this->hasMany(EmployeeRequest::class, 'reviewed_by');
    }

    public function createdRoastingRequests(): HasMany
    {
        return $this->hasMany(RoastingRequest::class, 'created_by');
    }

    public function roastingAssignments(): HasMany
    {
        return $this->hasMany(RoastingRequest::class, 'assigned_to');
    }

    public function roastingStatusChanges(): HasMany
    {
        return $this->hasMany(RoastingStatusLog::class, 'changed_by');
    }

    public function createdDistributionShipments(): HasMany
    {
        return $this->hasMany(DistributionShipment::class, 'created_by');
    }

    public function distributionAssignments(): HasMany
    {
        return $this->hasMany(DistributionShipment::class, 'assigned_to');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'performed_by');
    }

    public function systemNotifications(): HasMany
    {
        return $this->hasMany(SystemNotification::class);
    }
}
