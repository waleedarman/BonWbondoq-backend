<?php

namespace App\Http\Controllers\Web;

use App\Models\DistributionShipment;
use App\Models\RoastingRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TestingReportController extends TestingBaseController
{
    public function performance(): View|RedirectResponse
    {
        if ($redirect = $this->requireRole([Role::MANAGER])) {
            return $redirect;
        }

        $totalShipments = DistributionShipment::count();
        $deliveredShipments = DistributionShipment::where('status', DistributionShipment::STATUS_DELIVERED)->count();

        return view('testing.reports.performance', [
            'totalOperations' => RoastingRequest::count() + $totalShipments,
            'roastingToday' => RoastingRequest::whereDate('created_at', today())->count(),
            'roastingWeek' => RoastingRequest::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'roastingMonth' => RoastingRequest::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'totalShipments' => $totalShipments,
            'distributionToday' => DistributionShipment::whereDate('created_at', today())->count(),
            'deliveredShipments' => $deliveredShipments,
            'deliveryRate' => $totalShipments > 0 ? round(($deliveredShipments / $totalShipments) * 100) : 0,
        ]);
    }
}
