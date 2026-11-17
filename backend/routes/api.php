<?php

use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuditController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\FaceController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PenaltyController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ResourceController;
use App\Http\Controllers\Api\V1\SettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'check.suspension'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        Route::apiResource('resources', ResourceController::class);
        Route::get('resources/{resource}/availability', [ResourceController::class, 'availability']);

        Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('bookings/recurring', [BookingController::class, 'recurring']);
        Route::post('bookings/bundled', [BookingController::class, 'bundled']);
        Route::post('bookings/{booking}/check-in', [BookingController::class, 'checkIn']);
        Route::post('bookings/{booking}/waitlist', [BookingController::class, 'waitlist']);
        Route::post('waitlist/{waitlistEntry}/claim', [BookingController::class, 'claimWaitlist']);
        Route::post('waitlist/{waitlistEntry}/decline', [BookingController::class, 'declineWaitlist']);

        Route::get('approvals/pending', [ApprovalController::class, 'pending']);
        Route::post('approvals/{approval}/approve', [ApprovalController::class, 'approve']);
        Route::post('approvals/{approval}/reject', [ApprovalController::class, 'reject']);

        Route::post('face/enroll', [FaceController::class, 'enroll']);
        Route::get('face/status', [FaceController::class, 'status']);
        Route::delete('face', [FaceController::class, 'destroy']);

        Route::get('loans', [LoanController::class, 'index']);
        Route::post('loans/{loan}/handover', [LoanController::class, 'handover']);
        Route::post('loans/{loan}/return', [LoanController::class, 'returnAsset']);
        Route::post('loans/{loan}/damage', [LoanController::class, 'reportDamage']);
        Route::post('loans/{loan}/override', [LoanController::class, 'override']);

        Route::get('me/strikes', [PenaltyController::class, 'strikes']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/device-token', [NotificationController::class, 'registerToken']);

        Route::middleware('role:admin')->group(function () {
            Route::apiResource('locations', LocationController::class);
            Route::apiResource('settings', SettingController::class)->only(['index', 'update']);
            Route::get('audit', [AuditController::class, 'index']);
            Route::post('strikes/{strike}/cancel', [PenaltyController::class, 'cancelStrike']);
            Route::prefix('reports')->group(function () {
                Route::get('room-utilization', [ReportController::class, 'roomUtilization']);
                Route::get('no-show-rate', [ReportController::class, 'noShowRate']);
                Route::get('popular-assets', [ReportController::class, 'popularAssets']);
                Route::get('verification-success', [ReportController::class, 'verificationSuccess']);
            });
        });
    });
});
