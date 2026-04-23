<?php

namespace App\Http\Controllers\Web;

use App\Models\DistributionShipment;
use App\Models\EmployeeRequest;
use App\Models\Product;
use App\Models\RoastingRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TestingManagerController extends TestingBaseController
{
    public function dashboard(): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $completedRoasting = RoastingRequest::where('status', RoastingRequest::STATUS_COMPLETED)->count();
        $incompleteRoasting = RoastingRequest::whereNotIn('status', [
            RoastingRequest::STATUS_COMPLETED,
            RoastingRequest::STATUS_CANCELLED,
        ])->count();

        return view('testing.manager.dashboard', [
            'stats' => [
                'completed_roasting' => $completedRoasting,
                'incomplete_roasting' => $incompleteRoasting,
                'distribution_jobs' => DistributionShipment::count(),
                'roasting_jobs' => RoastingRequest::count(),
                'pending_requests' => EmployeeRequest::where('status', EmployeeRequest::STATUS_PENDING)->count(),
                'low_stock' => Product::whereColumn('quantity', '<=', 'minimum_quantity')->count(),
            ],
            'recentRoasting' => RoastingRequest::with(['product', 'assignedEmployee'])
                ->latest()
                ->limit(6)
                ->get(),
            'recentShipments' => DistributionShipment::with(['product', 'assignedEmployee'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    public function employeeRequests(): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        return view('testing.manager.approvals', [
            'requests' => EmployeeRequest::with(['user.branch', 'reviewer'])
                ->where('status', EmployeeRequest::STATUS_PENDING)
                ->latest()
                ->get(),
            'roles' => Role::whereIn('slug', [
                Role::ROASTING_EMPLOYEE,
                Role::INVENTORY_EMPLOYEE,
                Role::DISTRIBUTION_EMPLOYEE,
            ])->orderBy('name')->get(),
        ]);
    }

    public function approveEmployeeRequest(Request $request, EmployeeRequest $employeeRequest): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::findOrFail($data['role_id']);

        if ($employeeRequest->status !== EmployeeRequest::STATUS_PENDING) {
            return back()->withErrors(['request' => 'يمكن قبول الطلبات المعلقة فقط.']);
        }

        if ($role->slug === Role::MANAGER) {
            return back()->withErrors(['role_id' => 'لا يمكن تحويل طلب موظف عام إلى مدير من صفحة الموافقات.']);
        }

        DB::transaction(function () use ($employeeRequest, $data): void {
            $employeeRequest->user->update([
                'role_id' => $data['role_id'],
                'is_active' => true,
                'approved_at' => now(),
                'approved_by' => Auth::id(),
            ]);

            $employeeRequest->update([
                'status' => EmployeeRequest::STATUS_ACCEPTED,
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);
        });

        return back()->with('status', 'تم قبول الموظف وتفعيل الحساب بنجاح.');
    }

    public function rejectEmployeeRequest(Request $request, EmployeeRequest $employeeRequest): RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($employeeRequest->status !== EmployeeRequest::STATUS_PENDING) {
            return back()->withErrors(['request' => 'يمكن رفض الطلبات المعلقة فقط.']);
        }

        $employeeRequest->update([
            'status' => EmployeeRequest::STATUS_REJECTED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        return back()->with('status', 'تم رفض طلب الانضمام.');
    }
}
