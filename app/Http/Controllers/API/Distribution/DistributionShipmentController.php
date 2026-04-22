<?php

namespace App\Http\Controllers\API\Distribution;

use App\Http\Controllers\Controller;
use App\Models\DistributionShipment;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DistributionShipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DistributionShipment::query()
            ->with(['product', 'creator', 'assignedEmployee', 'branch'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_code' => ['required', 'string', 'max:255', 'unique:distribution_shipments,shipment_code'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'destination' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'exists:branches,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $shipment = new DistributionShipment();
        $shipment->forceFill($data + [
            'status' => DistributionShipment::STATUS_PENDING,
            'created_by' => $request->user()->id,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment created successfully.',
            'data' => $shipment->load(['product', 'creator', 'assignedEmployee', 'branch']),
        ], 201);
    }

    public function show(DistributionShipment $distributionShipment): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Distribution shipment fetched successfully.',
            'data' => $distributionShipment->load(['product', 'creator', 'assignedEmployee', 'branch']),
        ]);
    }

    public function assignEmployee(Request $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

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

    public function updateStatus(Request $request, DistributionShipment $distributionShipment): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(DistributionShipment::STATUSES)],
            'notes' => ['nullable', 'string'],
        ]);

        $distributionShipment->forceFill($this->timestampsForStatus($data['status']) + [
            'status' => $data['status'],
            'notes' => $data['notes'] ?? $distributionShipment->notes,
        ])->save();

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

        DB::transaction(function () use ($request, $distributionShipment): void {
            $distributionShipment->forceFill([
                'status' => DistributionShipment::STATUS_DELIVERED,
                'delivered_at' => now(),
            ])->save();

            $product = Product::lockForUpdate()->find($distributionShipment->product_id);

            if ($product) {
                $product->quantity = max(0, (float) $product->quantity - (float) $distributionShipment->quantity);
                $product->save();
            }

            $movement = new InventoryMovement();
            $movement->forceFill([
                'product_id' => $distributionShipment->product_id,
                'branch_id' => $distributionShipment->branch_id,
                'movement_type' => InventoryMovement::TYPE_OUT,
                'quantity' => $distributionShipment->quantity,
                'reason' => InventoryMovement::REASON_SHIPMENT,
                'reference_type' => DistributionShipment::class,
                'reference_id' => $distributionShipment->id,
                'performed_by' => $request->user()->id,
                'notes' => 'Inventory reduced after shipment delivery.',
            ])->save();
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
}
