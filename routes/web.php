<?php

use App\Http\Controllers\Web\TestingAuthController;
use App\Http\Controllers\Web\TestingDistributionController;
use App\Http\Controllers\Web\TestingEmployeeController;
use App\Http\Controllers\Web\TestingInventoryController;
use App\Http\Controllers\Web\TestingManagerController;
use App\Http\Controllers\Web\TestingNotificationController;
use App\Http\Controllers\Web\TestingReportController;
use App\Http\Controllers\Web\TestingRoastingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('testing')->name('testing.')->group(function (): void {
    Route::get('/', fn () => redirect()->route('testing.login'))->name('home');
    Route::get('login', [TestingAuthController::class, 'login'])->name('login');
    Route::post('login', [TestingAuthController::class, 'authenticate'])->name('login.submit');
    Route::post('logout', [TestingAuthController::class, 'logout'])->name('logout');
    Route::get('register', [TestingAuthController::class, 'register'])->name('register');
    Route::post('register', [TestingAuthController::class, 'storeRegistration'])->name('register.submit');

    Route::get('employee/dashboard', [TestingEmployeeController::class, 'dashboard'])->name('employee.dashboard');

    Route::prefix('manager')->name('manager.')->group(function (): void {
        Route::get('dashboard', [TestingManagerController::class, 'dashboard'])->name('dashboard');
        Route::get('employee-requests', [TestingManagerController::class, 'employeeRequests'])->name('employee-requests');
        Route::post('employee-requests/{employeeRequest}/approve', [TestingManagerController::class, 'approveEmployeeRequest'])->name('employee-requests.approve');
        Route::post('employee-requests/{employeeRequest}/reject', [TestingManagerController::class, 'rejectEmployeeRequest'])->name('employee-requests.reject');
    });

    Route::prefix('roasting')->name('roasting.')->group(function (): void {
        Route::get('requests', [TestingRoastingController::class, 'index'])->name('index');
        Route::get('requests/create', [TestingRoastingController::class, 'create'])->name('create');
        Route::post('requests', [TestingRoastingController::class, 'store'])->name('store');
        Route::get('requests/{roastingRequest}', [TestingRoastingController::class, 'show'])->name('show');
        Route::post('requests/{roastingRequest}/assign', [TestingRoastingController::class, 'assign'])->name('assign');
        Route::post('requests/{roastingRequest}/status', [TestingRoastingController::class, 'updateStatus'])->name('status');
        Route::get('my-tasks', [TestingRoastingController::class, 'tasks'])->name('tasks');
        Route::post('my-tasks/{roastingRequest}/start', [TestingRoastingController::class, 'startTask'])->name('tasks.start');
        Route::post('my-tasks/{roastingRequest}/complete', [TestingRoastingController::class, 'completeTask'])->name('tasks.complete');
    });

    Route::prefix('inventory')->name('inventory.')->group(function (): void {
        Route::get('/', [TestingInventoryController::class, 'index'])->name('index');
        Route::get('products/create', [TestingInventoryController::class, 'create'])->name('products.create');
        Route::post('products', [TestingInventoryController::class, 'store'])->name('products.store');
        Route::post('products/{product}/quantity', [TestingInventoryController::class, 'updateQuantity'])->name('products.quantity.update');
        Route::post('products/{product}/minimum-quantity', [TestingInventoryController::class, 'updateMinimumQuantity'])->name('products.minimum-quantity.update');
        Route::get('movements', [TestingInventoryController::class, 'movements'])->name('movements');
        Route::post('movements', [TestingInventoryController::class, 'storeMovement'])->name('movements.store');
    });

    Route::prefix('distribution')->name('distribution.')->group(function (): void {
        Route::get('shipments', [TestingDistributionController::class, 'index'])->name('index');
        Route::get('shipments/create', [TestingDistributionController::class, 'create'])->name('create');
        Route::post('shipments', [TestingDistributionController::class, 'store'])->name('store');
        Route::post('shipments/{distributionShipment}/update-details', [TestingDistributionController::class, 'updateDetails'])->name('update-details');
        Route::post('shipments/{distributionShipment}/cancel', [TestingDistributionController::class, 'cancel'])->name('cancel');
        Route::post('shipments/{distributionShipment}/assign', [TestingDistributionController::class, 'assign'])->name('assign');
        Route::post('shipments/{distributionShipment}/status', [TestingDistributionController::class, 'updateStatus'])->name('status');
        Route::get('my-shipments', [TestingDistributionController::class, 'tasks'])->name('tasks');
        Route::post('my-shipments/{distributionShipment}/transfer', [TestingDistributionController::class, 'transfer'])->name('tasks.transfer');
        Route::post('my-shipments/{distributionShipment}/deliver', [TestingDistributionController::class, 'deliver'])->name('tasks.deliver');
    });

    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [TestingNotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [TestingNotificationController::class, 'markAsRead'])->name('read');
        Route::post('read-all', [TestingNotificationController::class, 'markAllAsRead'])->name('read-all');
    });

    Route::get('reports/performance', [TestingReportController::class, 'performance'])->name('reports.performance');
});
