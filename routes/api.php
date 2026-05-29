<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\BalanceController;
use App\Http\Controllers\Api\V1\BillingRecordController;
use App\Http\Controllers\Api\V1\ClearanceController;
use App\Http\Controllers\Api\V1\EnrolledStudentController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['web', 'portal.session', 'api.log'])->group(function () {
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/{payment}', [PaymentController::class, 'show']);
    Route::post('payments', [PaymentController::class, 'store']);
    Route::put('payments/{payment}', [PaymentController::class, 'update']);
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy']);
    Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm'])
        ->middleware('assesspay.role:cashier');
    Route::post('payments/{payment}/reverse', [PaymentController::class, 'reverse'])
        ->middleware('assesspay.role:cashier');

    Route::get('balances', [BalanceController::class, 'index']);
    Route::get('balances/{balance}', [BalanceController::class, 'show']);
    Route::post('billing-accounts/{billingAccount}/recalculate', [BalanceController::class, 'recalculate'])
        ->middleware('assesspay.role:cashier');
    Route::put('billing-accounts/{billingAccount}/balance', [BalanceController::class, 'update'])
        ->middleware('assesspay.role:cashier');

    Route::get('receipts', [ReceiptController::class, 'index']);
    Route::get('receipts/{receipt}', [ReceiptController::class, 'show']);

    Route::get('billing-records', [BillingRecordController::class, 'index']);
    Route::post('billing-records', [BillingRecordController::class, 'store'])
        ->middleware('assesspay.role:cashier');
    Route::get('billing-records/{billingRecord}', [BillingRecordController::class, 'show']);
    Route::put('billing-records/{billingRecord}', [BillingRecordController::class, 'update'])
        ->middleware('assesspay.role:cashier');
    Route::delete('billing-records/{billingRecord}', [BillingRecordController::class, 'destroy'])
        ->middleware('assesspay.role:cashier');
    Route::get('billing-accounts', [BillingRecordController::class, 'accounts']);
    Route::get('enrolled-students', [EnrolledStudentController::class, 'index'])
        ->middleware('assesspay.role:cashier');
    Route::post('enrolled-students/assessment', [EnrolledStudentController::class, 'storeAssessment'])
        ->middleware('assesspay.role:cashier');

    Route::get('financial-analytics', [AnalyticsController::class, 'index'])
        ->middleware('assesspay.role:admin,cashier');

    Route::get('search', SearchController::class);

    Route::get('clearance/{studentNumber}', [ClearanceController::class, 'show']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);
});
