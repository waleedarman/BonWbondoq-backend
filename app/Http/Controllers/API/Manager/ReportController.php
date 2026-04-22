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
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Dashboard report fetched successfully.',
            'data' => [
                'users' => [
                    'total' => User::count(),
                    'active' => User::where('is_active', true)->count(),
                    'inactive' => User::where('is_active', false)->count(),
                ],
                'inventory' => [
                    'products' => Product::count(),
                    'low_stock_products' => Product::whereColumn('quantity', '<=', 'minimum_quantity')->count(),
                    'movements_today' => InventoryMovement::whereDate('created_at', today())->count(),
                ],
                'roasting' => [
                    'total' => RoastingRequest::count(),
                    'pending' => RoastingRequest::where('status', RoastingRequest::STATUS_PENDING)->count(),
                    'in_progress' => RoastingRequest::where('status', RoastingRequest::STATUS_IN_PROGRESS)->count(),
                    'completed' => RoastingRequest::where('status', RoastingRequest::STATUS_COMPLETED)->count(),
                ],
                'distribution' => [
                    'total' => DistributionShipment::count(),
                    'pending' => DistributionShipment::where('status', DistributionShipment::STATUS_PENDING)->count(),
                    'transferred' => DistributionShipment::where('status', DistributionShipment::STATUS_TRANSFERRED)->count(),
                    'delivered' => DistributionShipment::where('status', DistributionShipment::STATUS_DELIVERED)->count(),
                ],
            ],
        ]);
    }

    public function performanceSummary(Request $request): JsonResponse
    {
        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();

        $roasting = RoastingRequest::query();
        $shipments = DistributionShipment::query();
        $movements = InventoryMovement::query();

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
