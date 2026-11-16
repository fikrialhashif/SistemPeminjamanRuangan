<?php

use App\Http\Controllers\QrDisplayController;
use App\Http\Controllers\Web\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::get('/resources/{resource}/qr', [QrDisplayController::class, 'show']);

Route::prefix('admin')->group(function () {
    Route::get('login', [AdminController::class, 'showLoginForm'])->name('admin.login');
    Route::post('login', [AdminController::class, 'login'])->name('admin.login.submit');
    Route::post('logout', [AdminController::class, 'logout'])->name('admin.logout');

    Route::middleware(['auth'])->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('approvals', [AdminController::class, 'approvals'])->name('admin.approvals');
        Route::post('approvals/{approval}/approve', [AdminController::class, 'approve'])->name('admin.approvals.approve');
        Route::post('approvals/{approval}/reject', [AdminController::class, 'reject'])->name('admin.approvals.reject');
        Route::get('resources', [AdminController::class, 'resources'])->name('admin.resources');
        Route::post('resources', [AdminController::class, 'storeResource'])->name('admin.resources.store');
        Route::get('audit', [AdminController::class, 'audit'])->name('admin.audit');
    });
});
