<?php

namespace App\Http\Controllers\API\Distribution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distribution\AssignDistributionShipmentRequest;
use App\Http\Requests\Distribution\StoreDistributionShipmentRequest;
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

class DistributionShipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DistributionShipment::query()
            ->with(['product', 'creator', 'assignedEmployee', 'branch'])
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

        if ($assignedTo) {
            $this->abortUnlessAssignableEmployee((int) $assignedTo, Role::DISTRIBUTION_EMPLOYEE);
        }

        $shipment = new DistributionShipment();
        $shipment->forceFill(array_merge($data, [
            'branch_id' => $product->branch_id,
            'status' => $assignedTo ? DistributionShipment::STATUS_READY_FOR_PICKUP : DistributionShipment::STATUS_PENDING,
            'created_by' => $request->user()->id,
            'prepared_at' => $assignedTo ? now() : null,
        ]))->save();

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment created successfully.',
            'data' => $shipment->load(['product', 'creator', 'assignedEmployee', 'branch']),
        ], 201);
    }

    public function show(DistributionShipment $distributionShipment): JsonResponse
    {
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment fetched successfully.',
            'data' => $distributionShipment->load(['product', 'creator', 'assignedEmployee', 'branch']),
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
            'data' => $distributionShipment->fresh(['product', 'creator', 'assignedEmployee', 'branch']),
        ]);
    }

    public function updateStatus(UpdateDistributionShipmentStatusRequest $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        DB::transaction(function () use ($data, $distributionShipment, $request): void {
            $previousStatus = $distributionShipment->status;

            $distributionShipment->forceFill($this->timestampsForStatus($data['status']) + [
                'status' => $data['status'],
                'notes' => $data['notes'] ?? $distributionShipment->notes,
            ])->save();

            if ($data['status'] === DistributionShipment::STATUS_DELIVERED && $previousStatus !== DistributionShipment::STATUS_DELIVERED) {
                $this->applyDeliveryEffects($distributionShipment, $request->user()->id);
                app(SystemNotificationService::class)->notifyShipmentDelivered($distributionShipment);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment status updated successfully.',
            'data' => $distributionShipment->fresh(['product', 'creator', 'assignedEmployee', 'branch']),
        ]);
    }

    public function myShipments(Request $request): JsonResponse
    {
        $query = DistributionShipment::query()
            ->with(['product', 'branch'])
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

    public function markTransferred(Request $request, DistributionShipment $distributionShipment): JsonResponse
    {
        if ($distributionShipment->assigned_to !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update shipments assigned to you.',
            ], 403);
        }
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        $distributionShipment->forceFill([
            'status' => DistributionShipment::STATUS_TRANSFERRED,
            'transferred_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Shipment marked as transferred.',
            'data' => $distributionShipment->fresh(['product', 'branch']),
        ]);
    }

    public function markDelivered(Request $request, DistributionShipment $distributionShipment): JsonResponse
    {
        if ($distributionShipment->assigned_to !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update shipments assigned to you.',
            ], 403);
        }
        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        DB::transaction(function () use ($request, $distributionShipment): void {
            $previousStatus = $distributionShipment->status;

            $distributionShipment->forceFill([
                'status' => DistributionShipment::STATUS_DELIVERED,
                'delivered_at' => now(),
            ])->save();

            if ($previousStatus !== DistributionShipment::STATUS_DELIVERED) {
                $this->applyDeliveryEffects($distributionShipment, $request->user()->id);
                app(SystemNotificationService::class)->notifyShipmentDelivered($distributionShipment);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Shipment marked as delivered.',
            'data' => $distributionShipment->fresh(['product', 'branch']),
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

    private function applyDeliveryEffects(DistributionShipment $distributionShipment, int $performedBy): void
    {
        $existingMovement = InventoryMovement::where('reference_type', DistributionShipment::class)
            ->where('reference_id', $distributionShipment->id)
            ->where('reason', InventoryMovement::REASON_SHIPMENT)
            ->exists();

        if ($existingMovement) {
            return;
        }

        $product = Product::where('branch_id', $distributionShipment->branch_id)
            ->lockForUpdate()
            ->find($distributionShipment->product_id);

        if (! $product) {
            return;
        }

        $previousQuantity = (float) $product->quantity;
        $product->quantity = max(0, (float) $product->quantity - (float) $distributionShipment->quantity);
        $product->save();

        app(SystemNotificationService::class)->notifyStockThreshold($product, $previousQuantity);

        $movement = new InventoryMovement();
        $movement->forceFill([
            'product_id' => $distributionShipment->product_id,
            'branch_id' => $distributionShipment->branch_id,
            'movement_type' => InventoryMovement::TYPE_OUT,
            'quantity' => $distributionShipment->quantity,
            'reason' => InventoryMovement::REASON_SHIPMENT,
            'reference_type' => DistributionShipment::class,
            'reference_id' => $distributionShipment->id,
            'performed_by' => $performedBy,
            'notes' => 'Inventory reduced after shipment delivery.',
        ])->save();
    }
}
