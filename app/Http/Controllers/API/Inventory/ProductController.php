<?php

namespace App\Http\Controllers\API\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with('branch')
            ->where('branch_id', $request->user()->branch_id)
            ->latest();

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only list products from your branch.',
            ], 403);
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity', '<=', 'minimum_quantity');
        }

        return response()->json([
            'success' => true,
            'message' => 'Products fetched successfully.',
            'data' => $query->paginate($request->integer('per_page', 15)),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['branch_id']) && (int) $data['branch_id'] !== $this->currentBranchId()) {
            return response()->json([
                'success' => false,
                'message' => 'Products can only be created inside your branch.',
            ], 422);
        }

        $product = new Product();
        $product->forceFill(array_merge($data, ['branch_id' => $request->user()->branch_id]))->save();

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product->load('branch'),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $this->abortUnlessCurrentBranch($product->branch_id);

        return response()->json([
            'success' => true,
            'message' => 'Product fetched successfully.',
            'data' => $product->load('branch'),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();
        $this->abortUnlessCurrentBranch($product->branch_id);

        $product->forceFill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product->fresh('branch'),
        ]);
    }
}
