<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JobOrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\TariffController;
use Illuminate\Support\Facades\Route;

// sign in: open to everyone; 5 tries a minute against password guessing
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'create'])->name('login');
    Route::post('login', [AuthController::class, 'store'])->middleware('throttle:5,1');
});

// everything else needs a signed-in user
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
    Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
    Route::resource('quotations', QuotationController::class);

    Route::get('job-orders', [JobOrderController::class, 'index'])->name('job-orders.index');
    Route::get('job-orders/{jobOrder}', [JobOrderController::class, 'show'])->name('job-orders.show');
    Route::patch('job-orders/{jobOrder}/status', [JobOrderController::class, 'updateStatus'])->name('job-orders.status');

    Route::post('job-orders/{jobOrder}/invoice', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    Route::resource('customers', CustomerController::class)->except('show');
    Route::resource('tariffs', TariffController::class)->except('show');
});
