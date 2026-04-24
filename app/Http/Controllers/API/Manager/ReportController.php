<?php

namespace App\Http\Controllers\API\Manager;

use App\Http\Controllers\Controller;
use App\Models\DistributionShipment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RoastingRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        return response()->json([
            'success' => true,
            'message' => 'Dashboard report fetched successfully.',
            'data' => [
                'users' => [
                    'total' => User::where('branch_id', $branchId)->count(),
                    'active' => User::where('branch_id', $branchId)->where('is_active', true)->count(),
                    'inactive' => User::where('branch_id', $branchId)->where('is_active', false)->count(),
                ],
                'inventory' => [
                    'products' => Product::where('branch_id', $branchId)->count(),
                    'low_stock_products' => Product::where('branch_id', $branchId)->whereColumn('quantity', '<=', 'minimum_quantity')->count(),
                    'movements_today' => InventoryMovement::where('branch_id', $branchId)->whereDate('created_at', today())->count(),
                ],
                'roasting' => [
                    'total' => RoastingRequest::where('branch_id', $branchId)->count(),
                    'pending' => RoastingRequest::where('branch_id', $branchId)->where('status', RoastingRequest::STATUS_PENDING)->count(),
                    'in_progress' => RoastingRequest::where('branch_id', $branchId)->where('status', RoastingRequest::STATUS_IN_PROGRESS)->count(),
                    'completed' => RoastingRequest::where('branch_id', $branchId)->where('status', RoastingRequest::STATUS_COMPLETED)->count(),
                ],
                'distribution' => [
                    'total' => DistributionShipment::where('branch_id', $branchId)->count(),
                    'pending' => DistributionShipment::where('branch_id', $branchId)->where('status', DistributionShipment::STATUS_PENDING)->count(),
                    'transferred' => DistributionShipment::where('branch_id', $branchId)->where('status', DistributionShipment::STATUS_TRANSFERRED)->count(),
                    'delivered' => DistributionShipment::where('branch_id', $branchId)->where('status', DistributionShipment::STATUS_DELIVERED)->count(),
                ],
            ],
        ]);
    }

    public function performanceSummary(Request $request): JsonResponse
    {
        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();

        $branchId = $request->user()->branch_id;

        $roasting = RoastingRequest::where('branch_id', $branchId);
        $shipments = DistributionShipment::where('branch_id', $branchId);
        $movements = InventoryMovement::where('branch_id', $branchId);

        if ($from) {
            $roasting->where('created_at', '>=', $from);
            $shipments->where('created_at', '>=', $from);
            $movements->where('created_at', '>=', $from);
        }

        if ($to) {
            $roasting->where('created_at', '<=', $to);
            $shipments->where('created_at', '<=', $to);
            $movements->where('created_at', '<=', $to);
        }

        return response()->json([
            'success' => true,
            'message' => 'Performance summary fetched successfully.',
            'data' => [
                'roasting' => [
                    'created' => (clone $roasting)->count(),
                    'completed' => (clone $roasting)->where('status', RoastingRequest::STATUS_COMPLETED)->count(),
                    'cancelled' => (clone $roasting)->where('status', RoastingRequest::STATUS_CANCELLED)->count(),
                    'total_quantity' => (clone $roasting)->sum('quantity'),
                ],
                'distribution' => [
                    'created' => (clone $shipments)->count(),
                    'delivered' => (clone $shipments)->where('status', DistributionShipment::STATUS_DELIVERED)->count(),
                    'cancelled' => (clone $shipments)->where('status', DistributionShipment::STATUS_CANCELLED)->count(),
                    'total_quantity' => (clone $shipments)->sum('quantity'),
                ],
                'inventory' => [
                    'movements' => (clone $movements)->count(),
                    'stock_in' => (clone $movements)->where('movement_type', InventoryMovement::TYPE_IN)->sum('quantity'),
                    'stock_out' => (clone $movements)->where('movement_type', InventoryMovement::TYPE_OUT)->sum('quantity'),
                    'adjustments' => (clone $movements)->where('movement_type', InventoryMovement::TYPE_ADJUSTMENT)->count(),
                ],
            ],
        ]);
    }
}
