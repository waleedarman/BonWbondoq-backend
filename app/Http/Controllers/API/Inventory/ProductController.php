<?php

namespace App\Http\Controllers\API\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with('branch')->latest();

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

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
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

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
            'category' => ['required', Rule::in(['raw_coffee', 'roasted_coffee', 'packaging_material', 'beverage', 'supply', 'other'])],
            'unit' => ['required', Rule::in(['kg', 'gram', 'piece', 'box', 'bottle', 'pack'])],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'minimum_quantity' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['required', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product = new Product();
        $product->forceFill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product->load('branch'),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Product fetched successfully.',
            'data' => $product->load('branch'),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product->id)],
            'category' => ['sometimes', 'required', Rule::in(['raw_coffee', 'roasted_coffee', 'packaging_material', 'beverage', 'supply', 'other'])],
            'unit' => ['sometimes', 'required', Rule::in(['kg', 'gram', 'piece', 'box', 'bottle', 'pack'])],
            'quantity' => ['sometimes', 'numeric', 'min:0'],
            'minimum_quantity' => ['sometimes', 'numeric', 'min:0'],
            'branch_id' => ['sometimes', 'required', 'exists:branches,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product->forceFill($data)->save();

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product->fresh('branch'),
        ]);
    }
}
