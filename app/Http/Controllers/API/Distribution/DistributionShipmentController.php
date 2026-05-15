<?php

namespace App\Http\Controllers\API\Distribution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distribution\AssignDistributionShipmentRequest;
use App\Http\Requests\Distribution\StoreDistributionShipmentRequest;
use App\Http\Requests\Distribution\UpdateDistributionShipmentRequest;
use App\Http\Requests\Distribution\UpdateDistributionShipmentStatusRequest;
use App\Models\DistributionShipment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionShipmentController extends Controller
{
    private const INVENTORY_REFERENCE_TYPE = 'distribution_shipment';

    public function index(Request $request): JsonResponse
    {
        $query = DistributionShipment::query()
            ->with(['product', 'creator', 'assignedEmployee', 'inventoryEmployee', 'branch'])
            ->where('branch_id', $request->user()->branch_id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only list shipments from your branch.',
            ], 403);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->integer('assigned_to'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipments fetched successfully.',
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function store(StoreDistributionShipmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['branch_id']) && (int) $data['branch_id'] !== $this->currentBranchId()) {
            return response()->json([
                'success' => false,
                'message' => 'Shipments can only be created inside your branch.',
            ], 422);
        }

        $product = Product::where('branch_id', $request->user()->branch_id)->findOrFail($data['product_id']);
        $assignedTo = $data['assigned_to'] ?? null;
        $inventoryAssignedTo = $data['inventory_assigned_to'] ?? null;

        if ($assignedTo) {
            $this->abortUnlessAssignableEmployee((int) $assignedTo, Role::DISTRIBUTION_EMPLOYEE);
        }

        if ($inventoryAssignedTo) {
            $this->abortUnlessAssignableEmployee((int) $inventoryAssignedTo, Role::INVENTORY_EMPLOYEE);
        }

        $shipment = DistributionShipment::create(array_merge($data, [
            'branch_id' => $product->branch_id,
            'status' => DistributionShipment::STATUS_PENDING,
            'created_by' => $request->user()->id,
            'prepared_at' => null,
            'notes' => $data['notes'] ?? null,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment created successfully.',
            'data' => $shipment->load(['product', 'creator', 'assignedEmployee', 'inventoryEmployee', 'branch']),
        ], 201);
    }

    public function show(DistributionShipment $distributionShipment): JsonResponse
    {
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment fetched successfully.',
            'data' => $distributionShipment->load(['product', 'creator', 'assignedEmployee', 'inventoryEmployee', 'branch']),
        ]);
    }

    public function update(UpdateDistributionShipmentRequest $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        if ($distributionShipment->status !== DistributionShipment::STATUS_PENDING || $distributionShipment->prepared_at !== null) {
            throw ValidationException::withMessages([
                'status' => 'Only pending, unprepared distribution shipments can be updated.',
            ]);
        }

        $product = Product::where('branch_id', $request->user()->branch_id)->findOrFail($data['product_id']);
        $assignedTo = $data['assigned_to'] ?? null;
        $inventoryAssignedTo = $data['inventory_assigned_to'] ?? null;

        if ($assignedTo) {
            $this->abortUnlessAssignableEmployee((int) $assignedTo, Role::DISTRIBUTION_EMPLOYEE);
        }

        if ($inventoryAssignedTo) {
            $this->abortUnlessAssignableEmployee((int) $inventoryAssignedTo, Role::INVENTORY_EMPLOYEE);
        }

        $distributionShipment->forceFill([
            'product_id' => $product->id,
            'branch_id' => $product->branch_id,
            'quantity' => $data['quantity'],
            'destination' => $data['destination'],
            'recipient_name' => $data['recipient_name'],
            'assigned_to' => $assignedTo,
            'inventory_assigned_to' => $inventoryAssignedTo,
            'notes' => $data['notes'] ?? null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment updated successfully.',
            'data' => $distributionShipment->fresh(['product', 'creator', 'assignedEmployee', 'inventoryEmployee', 'branch']),
        ]);
    }

    public function assignEmployee(AssignDistributionShipmentRequest $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);
        $this->abortUnlessAssignableEmployee($data['assigned_to'], Role::DISTRIBUTION_EMPLOYEE);

        $distributionShipment->forceFill([
            'assigned_to' => $data['assigned_to'],
            'status' => DistributionShipment::STATUS_READY_FOR_PICKUP,
            'prepared_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment assigned successfully.',
            'data' => $distributionShipment->fresh(['product', 'creator', 'assignedEmployee', 'inventoryEmployee', 'branch']),
        ]);
    }

    public function updateStatus(UpdateDistributionShipmentStatusRequest $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        DB::transaction(function () use ($data, $distributionShipment): void {
            $previousStatus = $distributionShipment->status;

            if ($previousStatus === DistributionShipment::STATUS_DELIVERED && $data['status'] === DistributionShipment::STATUS_CANCELLED) {
                abort(422, 'Delivered shipments cannot be cancelled.');
            }

            if ($data['status'] === DistributionShipment::STATUS_CANCELLED && $previousStatus === DistributionShipment::STATUS_TRANSFERRED) {
                $this->restoreTransferStock($distributionShipment, auth()->id());
            }

            $isAdminCancellation = $data['status'] === DistributionShipment::STATUS_CANCELLED;
            $cancellationNote = $isAdminCancellation
                ? ($data['notes'] ?? 'أُلغيت هذه الشحنة بقرار إداري.')
                : ($data['notes'] ?? $distributionShipment->notes);

            $distributionShipment->forceFill($this->timestampsForStatus($data['status']) + [
                'status' => $data['status'],
                'notes' => $cancellationNote,
            ])->save();

            if ($data['status'] === DistributionShipment::STATUS_DELIVERED && $previousStatus !== DistributionShipment::STATUS_DELIVERED) {
                app(SystemNotificationService::class)->notifyShipmentDelivered($distributionShipment);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment status updated successfully.',
            'data' => $distributionShipment->fresh(['product', 'creator', 'assignedEmployee', 'inventoryEmployee', 'branch']),
        ]);
    }


    public function destroy(DistributionShipment $distributionShipment): JsonResponse
    {
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        if ($distributionShipment->status !== DistributionShipment::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => 'Only cancelled distribution shipments can be deleted.',
            ]);
        }

        $distributionShipment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment deleted successfully.',
            'data' => null,
        ]);
    }

    public function preparationTasks(Request $request): JsonResponse
    {
        $query = DistributionShipment::query()
            ->with(['product', 'creator', 'assignedEmployee', 'inventoryEmployee', 'branch'])
            ->where('branch_id', $request->user()->branch_id)
            ->where(function ($query) {
                $query->where('status', DistributionShipment::STATUS_PENDING)
                    ->orWhere(function ($q) {
                        $q->whereIn('status', [
                            DistributionShipment::STATUS_READY_FOR_PICKUP,
                            DistributionShipment::STATUS_CANCELLED
                        ])
                        ->where('updated_at', '>=', now()->subDay());
                    });
            })
            ->where(function ($query) use ($request): void {
                $query->whereNull('inventory_assigned_to')
                    ->orWhere('inventory_assigned_to', $request->user()->id);
            })
            ->latest();

        return response()->json([
            'success' => true,
            'message' => 'Distribution preparation tasks fetched successfully.',
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function prepareForPickup(Request $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);
        $this->abortUnlessInventoryAssignee($request, $distributionShipment);

        DB::transaction(function () use ($data, $distributionShipment, $request): void {
            $shipment = DistributionShipment::whereKey($distributionShipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->abortUnlessCurrentBranch($shipment->branch_id);

            if ($shipment->status === DistributionShipment::STATUS_READY_FOR_PICKUP) {
                return;
            }

            if ($shipment->status !== DistributionShipment::STATUS_PENDING) {
                abort(422, 'Only pending distribution shipments can be prepared for pickup.');
            }

            if (!$this->deductShipmentStock($shipment, $request->user()->id)) {
                $shipment->forceFill([
                    'status' => DistributionShipment::STATUS_CANCELLED,
                    'notes' => 'تم إلغاء المهمة تلقائياً بسبب عدم توفر كمية كافية في المخزون.',
                ])->save();
                return;
            }

            $shipment->forceFill([
                'status' => DistributionShipment::STATUS_READY_FOR_PICKUP,
                'prepared_at' => now(),
                'notes' => $data['notes'] ?? $shipment->notes,
            ])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment prepared for pickup successfully.',
            'data' => $distributionShipment->fresh(['product', 'creator', 'assignedEmployee', 'inventoryEmployee', 'branch']),
        ]);
    }

    public function myShipments(Request $request): JsonResponse
    {
        $query = DistributionShipment::query()
            ->with(['product', 'inventoryEmployee', 'branch'])
            ->where('assigned_to', $request->user()->id)
            ->where('branch_id', $request->user()->branch_id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Assigned shipments fetched successfully.',
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function showMyShipment(Request $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $this->abortUnlessAssignedToCurrentUser($request, $distributionShipment, 'view');
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        return response()->json([
            'success' => true,
            'message' => 'Assigned shipment fetched successfully.',
            'data' => $distributionShipment->load(['product', 'inventoryEmployee', 'branch']),
        ]);
    }

    public function markTransferred(Request $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $this->abortUnlessAssignedToCurrentUser($request, $distributionShipment, 'update');
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        DB::transaction(function () use ($request, $distributionShipment): void {
            $shipment = DistributionShipment::whereKey($distributionShipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($shipment->status, [DistributionShipment::STATUS_TRANSFERRED, DistributionShipment::STATUS_DELIVERED], true)) {
                return;
            }

            if ($shipment->status === DistributionShipment::STATUS_CANCELLED) {
                abort(422, 'This shipment can no longer be transferred.');
            }

            if ($shipment->status === DistributionShipment::STATUS_PENDING) {
                abort(422, 'Shipment must be prepared by inventory before transfer.');
            }

            if (!$this->deductShipmentStock($shipment, $request->user()->id)) {
                $shipment->forceFill([
                    'status' => DistributionShipment::STATUS_CANCELLED,
                    'notes' => 'تم إلغاء عملية التوصيل تلقائياً بسبب عدم توفر كمية كافية في المخزون.',
                ])->save();
                return;
            }

            $shipment->forceFill([
                'status' => DistributionShipment::STATUS_TRANSFERRED,
                'transferred_at' => now(),
            ])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Shipment marked as transferred.',
            'data' => $distributionShipment->fresh(['product', 'inventoryEmployee', 'branch']),
        ]);
    }

    public function cancelTransfer(Request $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $this->abortUnlessAssignedToCurrentUser($request, $distributionShipment, 'update');
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        DB::transaction(function () use ($request, $distributionShipment): void {
            $shipment = DistributionShipment::whereKey($distributionShipment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($shipment->status !== DistributionShipment::STATUS_TRANSFERRED) {
                abort(422, 'Only transferred shipments can be reverted.');
            }

            $this->restoreTransferStock($shipment, $request->user()->id);

            $shipment->forceFill([
                'status' => DistributionShipment::STATUS_CANCELLED,
            ])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Shipment operation has been cancelled.',
            'data' => $distributionShipment->fresh(['product', 'inventoryEmployee', 'branch']),
        ]);
    }

    public function markDelivered(Request $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $this->abortUnlessAssignedToCurrentUser($request, $distributionShipment, 'update');
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        if ($distributionShipment->status === DistributionShipment::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'Cancelled shipments cannot be delivered.',
            ], 422);
        }

        DB::transaction(function () use ($distributionShipment): void {
            $previousStatus = $distributionShipment->status;

            $distributionShipment->forceFill([
                'status' => DistributionShipment::STATUS_DELIVERED,
                'delivered_at' => now(),
            ])->save();

            if ($previousStatus !== DistributionShipment::STATUS_DELIVERED) {
                app(SystemNotificationService::class)->notifyShipmentDelivered($distributionShipment);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Shipment marked as delivered.',
            'data' => $distributionShipment->fresh(['product', 'inventoryEmployee', 'branch']),
        ]);
    }

    private function timestampsForStatus(string $status): array
    {
        return match ($status) {
            DistributionShipment::STATUS_READY_FOR_PICKUP => ['prepared_at' => now()],
            DistributionShipment::STATUS_TRANSFERRED => ['transferred_at' => now()],
            DistributionShipment::STATUS_DELIVERED => ['delivered_at' => now()],
            default => [],
        };
    }

    private function abortUnlessAssignableEmployee(int $userId, string $roleSlug): void
    {
        $exists = User::where('id', $userId)
            ->where('branch_id', $this->currentBranchId())
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->exists();

        abort_unless($exists, 422, 'Assigned employee must belong to your branch and role.');
    }

    private function abortUnlessAssignedToCurrentUser(Request $request, DistributionShipment $distributionShipment, string $action): void
    {
        abort_unless(
            $distributionShipment->assigned_to === $request->user()?->id,
            403,
            "You can only {$action} shipments assigned to you."
        );
    }

    private function abortUnlessInventoryAssignee(Request $request, DistributionShipment $distributionShipment): void
    {
        abort_unless(
            ! $distributionShipment->inventory_assigned_to || $distributionShipment->inventory_assigned_to === $request->user()?->id,
            403,
            'You can only prepare shipments assigned to you.'
        );
    }

    private function deductShipmentStock(DistributionShipment $shipment, int $performedBy): bool
    {
        $alreadyDeducted = InventoryMovement::where('reference_type', self::INVENTORY_REFERENCE_TYPE)
            ->where('reference_id', $shipment->id)
            ->where('movement_type', InventoryMovement::TYPE_OUT)
            ->exists();

        if ($alreadyDeducted) {
            return true;
        }

        $product = Product::where('branch_id', $shipment->branch_id)
            ->lockForUpdate()
            ->findOrFail($shipment->product_id);

        $previousQuantity = (float) $product->quantity;
        $shipmentQuantity = (float) $shipment->quantity;

        if ($previousQuantity < $shipmentQuantity) {
            return false;
        }

        $product->quantity = $previousQuantity - $shipmentQuantity;
        $product->save();

        app(SystemNotificationService::class)->notifyStockThreshold($product, $previousQuantity);

        InventoryMovement::create([
            'product_id' => $shipment->product_id,
            'branch_id' => $shipment->branch_id,
            'performed_by' => $performedBy,
            'movement_type' => InventoryMovement::TYPE_OUT,
            'reason' => InventoryMovement::REASON_MANUAL_ADJUSTMENT,
            'quantity' => $shipment->quantity,
            'reference_type' => self::INVENTORY_REFERENCE_TYPE,
            'reference_id' => $shipment->id,
            'notes' => "{$shipment->shipment_code} - خصم تلقائي عند بدء مهمة التوزيع",
        ]);

        return true;
    }

    private function restoreTransferStock(DistributionShipment $shipment, int $performedBy): void
    {
        $deductionExists = InventoryMovement::where('reference_type', self::INVENTORY_REFERENCE_TYPE)
            ->where('reference_id', $shipment->id)
            ->where('movement_type', InventoryMovement::TYPE_OUT)
            ->exists();

        if (! $deductionExists) {
            return;
        }

        $alreadyReturned = InventoryMovement::where('reference_type', self::INVENTORY_REFERENCE_TYPE)
            ->where('reference_id', $shipment->id)
            ->where('movement_type', InventoryMovement::TYPE_IN)
            ->exists();

        if ($alreadyReturned) {
            return;
        }

        $product = Product::where('branch_id', $shipment->branch_id)
            ->lockForUpdate()
            ->findOrFail($shipment->product_id);

        $product->quantity = (float) $product->quantity + (float) $shipment->quantity;
        $product->save();

        InventoryMovement::create([
            'product_id' => $shipment->product_id,
            'branch_id' => $shipment->branch_id,
            'performed_by' => $performedBy,
            'movement_type' => InventoryMovement::TYPE_IN,
            'reason' => InventoryMovement::REASON_RETURN,
            'quantity' => $shipment->quantity,
            'reference_type' => self::INVENTORY_REFERENCE_TYPE,
            'reference_id' => $shipment->id,
            'notes' => "{$shipment->shipment_code} - إرجاع تلقائي عند إلغاء مهمة التوزيع",
        ]);
    }
}
