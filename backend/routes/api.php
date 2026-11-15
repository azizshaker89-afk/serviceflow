<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\Public\PublicServiceCategoryController;
use App\Http\Controllers\Api\Public\PublicServiceRequestController;
use App\Http\Controllers\Api\Public\PublicStatsController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ServiceRequestController;
use App\Http\Controllers\Api\ServiceRequestPartController;
use App\Http\Controllers\Api\ServiceRequestWorkflowController;
use App\Http\Controllers\Api\SparePartController;
use App\Http\Controllers\Api\TechnicianController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
| Public: the customer-facing site. No login, rate limited per IP.
*/
Route::prefix('public')->name('public.')->group(function () {
    Route::get('service-categories', PublicServiceCategoryController::class)->name('service-categories');
    Route::get('stats', PublicStatsController::class)->name('stats');
    Route::post('service-requests', [PublicServiceRequestController::class, 'store'])
        ->middleware('throttle:public-requests')->name('service-requests.store');
    Route::post('service-requests/track', [PublicServiceRequestController::class, 'track'])
        ->middleware('throttle:tracking')->name('service-requests.track');
});

/*
| Staff authentication (Sanctum SPA cookies).
*/
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });
});

/*
| Control panel. Every route is authorized again by a Policy.
*/
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('service-categories', PublicServiceCategoryController::class)->name('service-categories');
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'update']);
    Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('technicians', [TechnicianController::class, 'index'])->name('technicians.index');

    Route::apiResource('service-requests', ServiceRequestController::class);

    Route::prefix('service-requests/{service_request}')
        ->name('service-requests.')
        ->controller(ServiceRequestWorkflowController::class)
        ->group(function () {
            Route::post('assign', 'assign')->name('assign');
            Route::post('start', 'start')->name('start');
            Route::post('pause', 'pause')->name('pause');
            Route::post('resume', 'resume')->name('resume');
            Route::post('work-logs', 'logWork')->name('work-logs.store');
            Route::post('complete', 'complete')->name('complete');
            Route::post('cancel', 'cancel')->name('cancel');
        });

    Route::post('service-requests/{service_request}/parts', [ServiceRequestPartController::class, 'store'])->name('service-requests.parts.store');
    Route::delete('service-requests/{service_request}/parts/{part}', [ServiceRequestPartController::class, 'destroy'])->name('service-requests.parts.destroy');

    Route::get('service-requests/{service_request}/invoice-preview', [InvoiceController::class, 'preview'])->name('service-requests.invoice-preview');
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'store']);
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

    Route::apiResource('spare-parts', SparePartController::class)->only(['index', 'store', 'update']);
    Route::post('spare-parts/{spare_part}/stock', [SparePartController::class, 'adjustStock'])->name('spare-parts.stock');
    Route::get('spare-parts/{spare_part}/movements', [SparePartController::class, 'movements'])->name('spare-parts.movements');
});
