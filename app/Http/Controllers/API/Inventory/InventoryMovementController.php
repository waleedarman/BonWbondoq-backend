<?php

namespace App\Http\Controllers\API\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\SystemNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InventoryMovement::query()
            ->with(['product', 'branch', 'performer'])
            ->where('branch_id', $request->user()->branch_id)
            ->latest();

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only list inventory movements from your branch.',
            ], 403);
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->string('movement_type'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Inventory movements fetched successfully.',
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function store(StoreInventoryMovementRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['branch_id']) && (int) $data['branch_id'] !== $this->currentBranchId()) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory movements can only be created inside your branch.',
            ], 422);
        }

        $movement = DB::transaction(function () use ($request, $data): InventoryMovement {
            $product = Product::where('branch_id', $request->user()->branch_id)->lockForUpdate()->findOrFail($data['product_id']);
            $previousQuantity = (float) $product->quantity;
            $quantity = (float) $data['quantity'];

            if ($data['movement_type'] === InventoryMovement::TYPE_IN) {
                $product->quantity = (float) $product->quantity + $quantity;
            } elseif ($data['movement_type'] === InventoryMovement::TYPE_OUT) {
                $product->quantity = max(0, (float) $product->quantity - $quantity);
            } else {
                $product->quantity = $quantity;
            }

            $product->save();

            app(SystemNotificationService::class)->notifyStockThreshold($product, $previousQuantity);

            $movement = new InventoryMovement();
            $movement->forceFill(array_merge($data, [
                'branch_id' => $product->branch_id,
                'performed_by' => $request->user()->id,
            ]))->save();

            return $movement;
        });

        return response()->json([
            'success' => true,
            'message' => 'Inventory movement created successfully.',
            'data' => $movement->load(['product', 'branch', 'performer']),
        ], 201);
    }
}
