<?php

use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Common\BranchController;
use App\Http\Controllers\API\Common\NotificationController;
use App\Http\Controllers\API\Distribution\DistributionShipmentController;
use App\Http\Controllers\API\Inventory\InventoryMovementController;
use App\Http\Controllers\API\Inventory\ProductController;
use App\Http\Controllers\API\Manager\EmployeeRequestController;
use App\Http\Controllers\API\Manager\ReportController;
use App\Http\Controllers\API\Manager\UserManagementController;
use App\Http\Controllers\API\Roasting\RoastingRequestController;
use Illuminate\Support\Facades\Route;

Route::post('register', [AuthController::class, 'register'])->name('auth.register');
Route::post('login', [AuthController::class, 'login'])->name('auth.login');
Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
Route::post('verify-reset-code', [AuthController::class, 'verifyResetCode'])->name('auth.verify-reset-code');
Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
Route::get('branches', [BranchController::class, 'index'])->name('branches.index');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('auth.me');

    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
    });

    Route::prefix('manager')->middleware('role:manager')->name('manager.')->group(function (): void {
        Route::prefix('employee-requests')->name('employee-requests.')->group(function (): void {
            Route::get('/', [EmployeeRequestController::class, 'index'])->name('index');
            Route::get('{employeeRequest}', [EmployeeRequestController::class, 'show'])->name('show');
            Route::post('{employeeRequest}/approve', [EmployeeRequestController::class, 'approve'])->name('approve');
            Route::post('{employeeRequest}/reject', [EmployeeRequestController::class, 'reject'])->name('reject');
        });

        Route::prefix('users')->name('users.')->group(function (): void {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::get('{user}', [UserManagementController::class, 'show'])->name('show');
            Route::patch('{user}/assign-role', [UserManagementController::class, 'assignRole'])->name('assign-role');
            Route::patch('{user}/activate', [UserManagementController::class, 'activate'])->name('activate');
            Route::patch('{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('reports')->name('reports.')->group(function (): void {
            Route::get('dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
            Route::get('performance-summary', [ReportController::class, 'performanceSummary'])->name('performance-summary');
        });
    });

    Route::prefix('inventory')->middleware('role:manager,inventory_employee')->name('inventory.')->group(function (): void {
        Route::prefix('products')->name('products.')->group(function (): void {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('{product}', [ProductController::class, 'show'])->name('show');
            Route::put('{product}', [ProductController::class, 'update'])->name('update');
        });

        Route::prefix('movements')->name('movements.')->group(function (): void {
            Route::get('/', [InventoryMovementController::class, 'index'])->name('index');
            Route::post('/', [InventoryMovementController::class, 'store'])->name('store');
        });
    });

    Route::prefix('roasting')->name('roasting.')->group(function (): void {
        Route::middleware('role:manager')->group(function (): void {
            Route::get('requests', [RoastingRequestController::class, 'index'])->name('requests.index');
            Route::post('requests', [RoastingRequestController::class, 'store'])->name('requests.store');
            Route::get('requests/{roastingRequest}', [RoastingRequestController::class, 'show'])->name('requests.show');
            Route::patch('requests/{roastingRequest}/assign', [RoastingRequestController::class, 'assignEmployee'])->name('requests.assign');
            Route::patch('requests/{roastingRequest}/status', [RoastingRequestController::class, 'updateStatus'])->name('requests.status');
        });

        Route::middleware('role:roasting_employee')->group(function (): void {
            Route::get('my-tasks', [RoastingRequestController::class, 'myTasks'])->name('my-tasks.index');
            Route::post('my-tasks/{roastingRequest}/start', [RoastingRequestController::class, 'startTask'])->name('my-tasks.start');
            Route::post('my-tasks/{roastingRequest}/complete', [RoastingRequestController::class, 'completeTask'])->name('my-tasks.complete');
        });
    });

    Route::prefix('distribution')->name('distribution.')->group(function (): void {
        Route::middleware('role:manager,inventory_employee')->group(function (): void {
            Route::get('preparation-tasks', [DistributionShipmentController::class, 'preparationTasks'])->name('preparation-tasks.index');
            Route::post('preparation-tasks/{distributionShipment}/prepare', [DistributionShipmentController::class, 'prepareForPickup'])->name('preparation-tasks.prepare');
        });

        Route::middleware('role:manager')->group(function (): void {
            Route::get('shipments', [DistributionShipmentController::class, 'index'])->name('shipments.index');
            Route::post('shipments', [DistributionShipmentController::class, 'store'])->name('shipments.store');
            Route::get('shipments/{distributionShipment}', [DistributionShipmentController::class, 'show'])->name('shipments.show');
            Route::patch('shipments/{distributionShipment}/assign', [DistributionShipmentController::class, 'assignEmployee'])->name('shipments.assign');
            Route::patch('shipments/{distributionShipment}/status', [DistributionShipmentController::class, 'updateStatus'])->name('shipments.status');
        });

        Route::middleware('role:distribution_employee')->group(function (): void {
            Route::get('my-shipments', [DistributionShipmentController::class, 'myShipments'])->name('my-shipments.index');
            Route::get('my-shipments/{distributionShipment}', [DistributionShipmentController::class, 'showMyShipment'])->name('my-shipments.show');
            Route::post('my-shipments/{distributionShipment}/transfer', [DistributionShipmentController::class, 'markTransferred'])->name('my-shipments.transfer');
            Route::post('my-shipments/{distributionShipment}/cancel-transfer', [DistributionShipmentController::class, 'cancelTransfer'])->name('my-shipments.cancel-transfer');
            Route::post('my-shipments/{distributionShipment}/deliver', [DistributionShipmentController::class, 'markDelivered'])->name('my-shipments.deliver');
        });
    });
});
