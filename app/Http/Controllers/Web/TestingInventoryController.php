<?php

namespace App\Http\Controllers\Web;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Services\SystemNotificationService;
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
            ->where('branch_id', $this->currentBranchId())
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
            'branches' => \App\Models\Branch::where('id', $this->currentBranchId())->where('is_active', true)->orderBy('name')->get(),
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
        $this->abortUnlessCurrentBranch((int) $data['branch_id']);

        Product::create($data + ['is_active' => true]);

        return redirect()->route('testing.inventory.index')->with('status', 'تمت إضافة المنتج إلى المخزون.');
    }

    public function updateQuantity(Request $request, Product $product): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::INVENTORY_EMPLOYEE])) {
            return $redirect;
        }

        $this->abortUnlessCurrentBranch($product->branch_id);

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($product, $data): void {
            $lockedProduct = Product::where('branch_id', Auth::user()->branch_id)
                ->lockForUpdate()
                ->findOrFail($product->id);

            $previousQuantity = (float) $lockedProduct->quantity;
            $lockedProduct->quantity = (float) $data['quantity'];
            $lockedProduct->save();

            app(SystemNotificationService::class)->notifyStockThreshold($lockedProduct, $previousQuantity);

            InventoryMovement::create([
                'product_id' => $lockedProduct->id,
                'branch_id' => $lockedProduct->branch_id,
                'movement_type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity' => (float) $data['quantity'],
                'reason' => InventoryMovement::REASON_MANUAL_ADJUSTMENT,
                'performed_by' => Auth::id(),
                'notes' => $data['notes'] ?? 'Manual quantity update from inventory dashboard.',
            ]);
        });

        return back()->with('status', 'تم تعديل الكمية يدويا وتسجيل الحركة بنجاح.');
    }

    public function updateMinimumQuantity(Request $request, Product $product): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::INVENTORY_EMPLOYEE])) {
            return $redirect;
        }

        $this->abortUnlessCurrentBranch($product->branch_id);

        $data = $request->validate([
            'minimum_quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $product->update([
            'minimum_quantity' => (float) $data['minimum_quantity'],
        ]);

        return back()->with('status', 'تم تعديل الحد الأدنى بنجاح.');
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
            ->where('branch_id', $this->currentBranchId())
            ->get();

        return view('testing.inventory.movements', [
            'movements' => $movements,
            'products' => Product::where('branch_id', $this->currentBranchId())->where('is_active', true)->orderBy('name')->get(),
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
            $product = Product::where('branch_id', Auth::user()->branch_id)->lockForUpdate()->findOrFail($data['product_id']);
            $previousQuantity = (float) $product->quantity;
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

            app(SystemNotificationService::class)->notifyStockThreshold($product, $previousQuantity);

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
