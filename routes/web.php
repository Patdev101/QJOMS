<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobOrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\TariffController;
use App\Http\Middleware\Disabled;
use Illuminate\Support\Facades\Route;

// open for now: the dashboard and making a new quotation
Route::get('/', DashboardController::class)->name('dashboard');
Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store');

// switched off for now (answer "not found"); remove the middleware to open them again
Route::middleware(Disabled::class)->group(function () {
    Route::resource('customers', CustomerController::class)->except('show');
    Route::resource('tariffs', TariffController::class)->except('show');

    Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
    Route::resource('quotations', QuotationController::class)->except(['create', 'store']);

    Route::get('job-orders', [JobOrderController::class, 'index'])->name('job-orders.index');
    Route::get('job-orders/{jobOrder}', [JobOrderController::class, 'show'])->name('job-orders.show');
    Route::patch('job-orders/{jobOrder}/status', [JobOrderController::class, 'updateStatus'])->name('job-orders.status');
});
