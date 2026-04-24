<?php

namespace App\Http\Controllers\Web;

use App\Models\DistributionShipment;
use App\Models\RoastingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TestingEmployeeController extends TestingBaseController
{
    public function dashboard(): View|RedirectResponse
    {
        if ($redirect = $this->requireUser()) {
            return $redirect;
        }

        return view('testing.employee.dashboard', [
            'roastingTasks' => RoastingRequest::with(['product', 'branch'])
                ->where('assigned_to', Auth::id())
                ->where('branch_id', Auth::user()->branch_id)
                ->latest()
                ->get(),
            'distributionTasks' => DistributionShipment::with(['product', 'branch'])
                ->where('assigned_to', Auth::id())
                ->where('branch_id', Auth::user()->branch_id)
                ->latest()
                ->get(),
        ]);
    }
}
