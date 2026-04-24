<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\RoastingRequest;
use App\Models\RoastingStatusLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

abstract class TestingBaseController extends Controller
{
    protected function requireUser(): ?RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()
                ->route('testing.login')
                ->withErrors(['auth' => 'يرجى تسجيل الدخول أولا.']);
        }

        return null;
    }

    protected function requireRole(array $roles): ?RedirectResponse
    {
        if ($redirect = $this->requireUser()) {
            return $redirect;
        }

        $role = Auth::user()->loadMissing('role')->role?->slug;

        if ($role === Role::MANAGER || in_array($role, $roles, true)) {
            return null;
        }

        return redirect()
            ->route($this->homeRouteFor(Auth::user()))
            ->withErrors(['auth' => 'هذه الصفحة غير متاحة لصلاحية حسابك الحالي.']);
    }

    protected function homeRouteFor(User $user): string
    {
        return match ($user->loadMissing('role')->role?->slug) {
            Role::ROASTING_EMPLOYEE => 'testing.roasting.tasks',
            Role::INVENTORY_EMPLOYEE => 'testing.inventory.index',
            Role::DISTRIBUTION_EMPLOYEE => 'testing.distribution.tasks',
            default => 'testing.manager.dashboard',
        };
    }

    protected function usersByRole(string $roleSlug)
    {
        return User::where('is_active', true)
            ->where('branch_id', Auth::user()?->branch_id)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->orderBy('name')
            ->get();
    }

    protected function currentBranchId(): int
    {
        $branchId = Auth::user()?->branch_id;

        abort_if(! $branchId, 403, 'يجب أن يكون الحساب مرتبطا بفرع.');

        return (int) $branchId;
    }

    protected function abortUnlessCurrentBranch(?int $branchId): void
    {
        abort_if((int) $branchId !== $this->currentBranchId(), 403, 'هذا السجل لا يتبع فرع حسابك.');
    }

    protected function logRoastingStatus(RoastingRequest $roastingRequest, string $status, ?string $note = null): void
    {
        RoastingStatusLog::create([
            'roasting_request_id' => $roastingRequest->id,
            'status' => $status,
            'changed_by' => Auth::id(),
            'note' => $note,
        ]);
    }
}
