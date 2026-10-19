<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobOrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\TariffController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve');
Route::resource('quotations', QuotationController::class);

Route::get('job-orders', [JobOrderController::class, 'index'])->name('job-orders.index');
Route::get('job-orders/{jobOrder}', [JobOrderController::class, 'show'])->name('job-orders.show');
Route::patch('job-orders/{jobOrder}/status', [JobOrderController::class, 'updateStatus'])->name('job-orders.status');

Route::resource('customers', CustomerController::class)->except('show');
Route::resource('tariffs', TariffController::class)->except('show');
