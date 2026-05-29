<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SsoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('assesspay.home');

// SSO handoff — module-bridge POSTs identity here after exchanging token with DEORIS
Route::match(['get', 'post'], '/sso/redirect', [DashboardController::class, 'complete'])->name('assesspay.sso.redirect');
Route::get('/sso/complete', [DashboardController::class, 'complete'])->name('assesspay.sso.complete');

Route::get('/api/sso/heartbeat', [SsoController::class, 'heartbeat'])->name('assesspay.sso.heartbeat');
Route::post('/api/sso/revoke', [SsoController::class, 'revoke'])->name('assesspay.sso.revoke');
Route::post('/sso/exchange', [SsoController::class, 'exchange'])->name('assesspay.sso.exchange');

Route::get('/admin', [DashboardController::class, 'admin'])->name('assesspay.admin');
Route::get('/admin/payments', [DashboardController::class, 'adminPayments'])->name('assesspay.admin.payments');
Route::get('/admin/receipts', [DashboardController::class, 'adminReceipts'])->name('assesspay.admin.receipts');
Route::get('/admin/analytics', [DashboardController::class, 'adminAnalytics'])->name('assesspay.admin.analytics');
Route::get('/admin/history', [DashboardController::class, 'adminHistory'])->name('assesspay.admin.history');

Route::get('/cashier', [DashboardController::class, 'cashier'])->name('assesspay.cashier');
Route::post('/cashier/payables', [DashboardController::class, 'storePayable'])->name('assesspay.cashier.payables');
Route::get('/cashier/payments', [DashboardController::class, 'cashierPayments'])->name('assesspay.cashier.payments');
Route::get('/cashier/receipts', [DashboardController::class, 'cashierReceipts'])->name('assesspay.cashier.receipts');
Route::get('/cashier/history', [DashboardController::class, 'cashierHistory'])->name('assesspay.cashier.history');

Route::get('/student', [DashboardController::class, 'student'])->name('assesspay.student');
Route::get('/student/payments', [DashboardController::class, 'studentPayments'])->name('assesspay.student.payments');
Route::get('/student/receipts', [DashboardController::class, 'studentReceipts'])->name('assesspay.student.receipts');
