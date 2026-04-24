<?php

namespace App\Http\Controllers\Web;

use App\Models\DistributionShipment;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Services\SystemNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TestingDistributionController extends TestingBaseController
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $shipments = DistributionShipment::with(['product', 'creator', 'assignedEmployee', 'branch'])
            ->where('branch_id', $this->currentBranchId())
            ->when($request->query('search'), function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('shipment_code', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), function ($query, string $status): void {
                $query->where('status', $status);
            })
            ->latest()
            ->get();

        return view('testing.distribution.index', [
            'shipments' => $shipments,
            'employees' => $this->usersByRole(Role::DISTRIBUTION_EMPLOYEE),
            'statuses' => DistributionShipment::STATUSES,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        return view('testing.distribution.create', [
            'products' => Product::with('branch')
                ->where('branch_id', $this->currentBranchId())
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'employees' => $this->usersByRole(Role::DISTRIBUTION_EMPLOYEE),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'destination' => ['required', 'string', 'max:255'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'assigned_to' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $product = Product::where('branch_id', $this->currentBranchId())->findOrFail($data['product_id']);

        abort_unless(
            $this->usersByRole(Role::DISTRIBUTION_EMPLOYEE)->contains('id', (int) $data['assigned_to']),
            422,
            'الموظف يجب أن يكون من نفس الفرع.'
        );

        if ((float) $data['quantity'] > (float) $product->quantity) {
            return back()
                ->withErrors(['quantity' => 'كمية الشحنة أكبر من الكمية المتاحة في المخزون.'])
                ->withInput();
        }

        DistributionShipment::create([
            'shipment_code' => $this->generateShipmentCode(),
            'product_id' => $product->id,
            'quantity' => $data['quantity'],
            'destination' => $data['destination'],
            'recipient_name' => $data['recipient_name'],
            'assigned_to' => $data['assigned_to'],
            'status' => DistributionShipment::STATUS_READY_FOR_PICKUP,
            'created_by' => Auth::id(),
            'branch_id' => $product->branch_id,
            'prepared_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('testing.distribution.index')
            ->with('status', 'تم إنشاء شحنة التوزيع وتعيين الموزع مباشرة.');
    }

    public function updateDetails(Request $request, DistributionShipment $distributionShipment): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'destination' => ['required', 'string', 'max:255'],
        ]);

        abort_unless(
            $this->usersByRole(Role::DISTRIBUTION_EMPLOYEE)->contains('id', (int) $data['assigned_to']),
            422,
            'الموظف يجب أن يكون من نفس الفرع.'
        );

        $updates = [
            'assigned_to' => $data['assigned_to'],
            'destination' => $data['destination'],
        ];

        if ($distributionShipment->status === DistributionShipment::STATUS_PENDING) {
            $updates['status'] = DistributionShipment::STATUS_READY_FOR_PICKUP;
            $updates['prepared_at'] = $distributionShipment->prepared_at ?? now();
        }

        $distributionShipment->update($updates);

        return back()->with('status', 'تم تحديث الموزع والوجهة بنجاح.');
    }

    public function cancel(DistributionShipment $distributionShipment): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        if ($distributionShipment->status === DistributionShipment::STATUS_DELIVERED) {
            return back()->withErrors(['shipment' => 'لا يمكن إلغاء شحنة تم تسليمها بالفعل.']);
        }

        $distributionShipment->update([
            'status' => DistributionShipment::STATUS_CANCELLED,
        ]);

        return back()->with('status', 'تم إلغاء الشحنة.');
    }

    public function assign(Request $request, DistributionShipment $distributionShipment): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);

        abort_unless(
            $this->usersByRole(Role::DISTRIBUTION_EMPLOYEE)->contains('id', (int) $data['assigned_to']),
            422,
            'الموظف يجب أن يكون من نفس الفرع.'
        );

        $distributionShipment->update([
            'assigned_to' => $data['assigned_to'],
            'status' => DistributionShipment::STATUS_READY_FOR_PICKUP,
            'prepared_at' => $distributionShipment->prepared_at ?? now(),
        ]);

        return back()->with('status', 'تم تعيين موظف التوزيع وتحضير الشحنة للاستلام.');
    }

    public function updateStatus(Request $request, DistributionShipment $distributionShipment): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(DistributionShipment::STATUSES)],
        ]);

        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);
        $this->updateShipmentStatus($distributionShipment, $data['status']);

        return back()->with('status', 'تم تحديث حالة الشحنة.');
    }

    public function tasks(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::DISTRIBUTION_EMPLOYEE])) {
            return $redirect;
        }

        $shipments = DistributionShipment::with(['product', 'branch'])
            ->where('assigned_to', Auth::id())
            ->where('branch_id', $this->currentBranchId())
            ->when($request->query('search'), function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('shipment_code', 'like', "%{$search}%")
                        ->orWhere('destination', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), function ($query, string $status): void {
                $query->where('status', $status);
            })
            ->latest()
            ->get();

        return view('testing.distribution.tasks', [
            'shipments' => $shipments,
            'statuses' => DistributionShipment::STATUSES,
        ]);
    }

    public function transfer(DistributionShipment $distributionShipment): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::DISTRIBUTION_EMPLOYEE])) {
            return $redirect;
        }

        if ($distributionShipment->assigned_to !== Auth::id()) {
            return back()->withErrors(['shipment' => 'هذه الشحنة غير مخصصة لك.']);
        }

        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);
        $this->updateShipmentStatus($distributionShipment, DistributionShipment::STATUS_TRANSFERRED);

        return back()->with('status', 'تم نقل الشحنة.');
    }

    public function deliver(DistributionShipment $distributionShipment): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::DISTRIBUTION_EMPLOYEE])) {
            return $redirect;
        }

        if ($distributionShipment->assigned_to !== Auth::id()) {
            return back()->withErrors(['shipment' => 'هذه الشحنة غير مخصصة لك.']);
        }

        $this->abortUnlessCurrentBranch($distributionShipment->branch_id);
        $this->updateShipmentStatus($distributionShipment, DistributionShipment::STATUS_DELIVERED);

        return back()->with('status', 'تم تسليم الشحنة وتحديث حركة المخزون.');
    }

    private function updateShipmentStatus(DistributionShipment $distributionShipment, string $status): void
    {
        DB::transaction(function () use ($distributionShipment, $status): void {
            $oldStatus = $distributionShipment->status;
            $updates = ['status' => $status];

            if ($status === DistributionShipment::STATUS_READY_FOR_PICKUP) {
                $updates['prepared_at'] = $distributionShipment->prepared_at ?? now();
            }

            if ($status === DistributionShipment::STATUS_TRANSFERRED) {
                $updates['transferred_at'] = $distributionShipment->transferred_at ?? now();
            }

            if ($status === DistributionShipment::STATUS_DELIVERED) {
                $updates['delivered_at'] = $distributionShipment->delivered_at ?? now();
            }

            $distributionShipment->update($updates);

            if ($status === DistributionShipment::STATUS_DELIVERED && $oldStatus !== DistributionShipment::STATUS_DELIVERED) {
                $this->applyDeliveryEffects($distributionShipment);
                app(SystemNotificationService::class)->notifyShipmentDelivered($distributionShipment);
            }
        });
    }

    private function applyDeliveryEffects(DistributionShipment $distributionShipment): void
    {
        $existingMovement = InventoryMovement::where('reference_type', DistributionShipment::class)
            ->where('reference_id', $distributionShipment->id)
            ->where('reason', InventoryMovement::REASON_SHIPMENT)
            ->exists();

        if ($existingMovement) {
            return;
        }

        $product = Product::where('branch_id', Auth::user()->branch_id)
            ->lockForUpdate()
            ->find($distributionShipment->product_id);

        if (! $product) {
            return;
        }

        $previousQuantity = (float) $product->quantity;
        $product->quantity = max(0, (float) $product->quantity - (float) $distributionShipment->quantity);
        $product->save();

        app(SystemNotificationService::class)->notifyStockThreshold($product, $previousQuantity);

        InventoryMovement::create([
            'product_id' => $distributionShipment->product_id,
            'branch_id' => $distributionShipment->branch_id,
            'movement_type' => InventoryMovement::TYPE_OUT,
            'quantity' => $distributionShipment->quantity,
            'reason' => InventoryMovement::REASON_SHIPMENT,
            'reference_type' => DistributionShipment::class,
            'reference_id' => $distributionShipment->id,
            'performed_by' => Auth::id(),
            'notes' => 'تم تسليم الشحنة من واجهة الاختبار.',
        ]);
    }

    private function generateShipmentCode(): string
    {
        do {
            $code = 'SHIP-' . now()->format('YmdHis') . '-' . random_int(100, 999);
        } while (DistributionShipment::where('shipment_code', $code)->exists());

        return $code;
    }
}
