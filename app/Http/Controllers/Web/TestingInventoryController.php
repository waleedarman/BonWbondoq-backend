<?php

namespace App\Http\Controllers\Web;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TestingInventoryController extends TestingBaseController
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::INVENTORY_EMPLOYEE])) {
            return $redirect;
        }

        $products = Product::with('branch')
            ->when($request->query('search'), function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->query('category'), function ($query, string $category): void {
                $query->where('category', $category);
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('testing.inventory.index', [
            'products' => $products,
            'categories' => ['raw_coffee', 'roasted_coffee', 'packaging_material', 'beverage', 'supply', 'other'],
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::INVENTORY_EMPLOYEE])) {
            return $redirect;
        }

        return view('testing.inventory.create', [
            'branches' => \App\Models\Branch::where('is_active', true)->orderBy('name')->get(),
            'categories' => ['raw_coffee', 'roasted_coffee', 'packaging_material', 'beverage', 'supply', 'other'],
            'units' => ['kg', 'gram', 'piece', 'box', 'bottle', 'pack'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::INVENTORY_EMPLOYEE])) {
            return $redirect;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:products,sku'],
            'category' => ['required', Rule::in(['raw_coffee', 'roasted_coffee', 'packaging_material', 'beverage', 'supply', 'other'])],
            'unit' => ['required', Rule::in(['kg', 'gram', 'piece', 'box', 'bottle', 'pack'])],
            'quantity' => ['required', 'numeric', 'min:0'],
            'minimum_quantity' => ['required', 'numeric', 'min:0'],
            'branch_id' => ['required', 'exists:branches,id'],
        ]);

        Product::create($data + ['is_active' => true]);

        return redirect()->route('testing.inventory.index')->with('status', 'تمت إضافة المنتج إلى المخزون.');
    }

    public function movements(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::INVENTORY_EMPLOYEE])) {
            return $redirect;
        }

        $movements = InventoryMovement::with(['product', 'branch', 'performer'])
            ->when($request->query('movement_type'), function ($query, string $type): void {
                $query->where('movement_type', $type);
            })
            ->when($request->query('reason'), function ($query, string $reason): void {
                $query->where('reason', $reason);
            })
            ->when($request->query('month'), function ($query, string $month): void {
                $query->whereYear('created_at', substr($month, 0, 4))
                    ->whereMonth('created_at', substr($month, 5, 2));
            })
            ->latest()
            ->get();

        return view('testing.inventory.movements', [
            'movements' => $movements,
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'types' => InventoryMovement::TYPES,
            'reasons' => InventoryMovement::REASONS,
        ]);
    }

    public function storeMovement(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::INVENTORY_EMPLOYEE])) {
            return $redirect;
        }

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'movement_type' => ['required', Rule::in(InventoryMovement::TYPES)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', Rule::in(InventoryMovement::REASONS)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($data): void {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);
            $quantity = (float) $data['quantity'];

            if ($data['movement_type'] === InventoryMovement::TYPE_OUT && (float) $product->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'الكمية المطلوبة أكبر من الكمية المتاحة في المخزون.',
                ]);
            }

            if ($data['movement_type'] === InventoryMovement::TYPE_IN) {
                $product->quantity = (float) $product->quantity + $quantity;
            } elseif ($data['movement_type'] === InventoryMovement::TYPE_OUT) {
                $product->quantity = (float) $product->quantity - $quantity;
            } else {
                $product->quantity = $quantity;
            }

            $product->save();

            InventoryMovement::create([
                'product_id' => $product->id,
                'branch_id' => $product->branch_id,
                'movement_type' => $data['movement_type'],
                'quantity' => $quantity,
                'reason' => $data['reason'],
                'performed_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return back()->with('status', 'تم تسجيل حركة المخزون وتحديث الكمية.');
    }
}
