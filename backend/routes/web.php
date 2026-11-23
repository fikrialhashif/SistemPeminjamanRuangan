<?php

use App\Http\Controllers\QrDisplayController;
use App\Http\Controllers\Web\AccessManagementController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\AdminManagementController;
use App\Http\Controllers\Web\MasterDataController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::get('/resources/{resource}/qr', [QrDisplayController::class, 'show'])->name('resources.qr');
Route::get('/resources/{resource}/label', [QrDisplayController::class, 'assetLabel'])->name('resources.asset-label');

Route::prefix('admin')->group(function () {
    Route::get('login', [AdminController::class, 'showLoginForm'])->name('admin.login');
    Route::post('login', [AdminController::class, 'login'])->middleware('throttle:5,1')->name('admin.login.submit');
    Route::post('logout', [AdminController::class, 'logout'])->name('admin.logout');

    Route::middleware(['auth'])->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('approvals', [AdminController::class, 'approvals'])->name('admin.approvals');
        Route::post('approvals/{approval}/approve', [AdminController::class, 'approve'])->name('admin.approvals.approve');
        Route::post('approvals/{approval}/reject', [AdminController::class, 'reject'])->name('admin.approvals.reject');
        Route::get('resources', [AdminController::class, 'resources'])->name('admin.resources');
        Route::post('resources', [AdminController::class, 'storeResource'])->name('admin.resources.store');
        Route::get('resources/{resource}/edit', [AdminController::class, 'editResource'])->name('admin.resources.edit');
        Route::put('resources/{resource}', [AdminController::class, 'updateResource'])->name('admin.resources.update');
        Route::patch('resources/{resource}/toggle', [AdminController::class, 'toggleResource'])->name('admin.resources.toggle');
        Route::delete('resources/{resource}', [AdminController::class, 'destroyResource'])->name('admin.resources.destroy');
        Route::get('audit', [AdminController::class, 'audit'])->name('admin.audit');

        Route::get('locations', [AdminManagementController::class, 'locations'])->name('admin.locations');
        Route::post('locations', [AdminManagementController::class, 'storeLocation'])->name('admin.locations.store');
        Route::put('locations/{location}', [AdminManagementController::class, 'updateLocation'])->name('admin.locations.update');
        Route::delete('locations/{location}', [AdminManagementController::class, 'destroyLocation'])->name('admin.locations.destroy');

        Route::get('categories', [MasterDataController::class, 'categories'])->name('admin.categories');
        Route::post('categories', [MasterDataController::class, 'storeCategory'])->name('admin.categories.store');
        Route::put('categories/{category}', [MasterDataController::class, 'updateCategory'])->name('admin.categories.update');
        Route::delete('categories/{category}', [MasterDataController::class, 'destroyCategory'])->name('admin.categories.destroy');

        Route::get('facilities', [MasterDataController::class, 'facilities'])->name('admin.facilities');
        Route::post('facilities', [MasterDataController::class, 'storeFacility'])->name('admin.facilities.store');
        Route::put('facilities/{facility}', [MasterDataController::class, 'updateFacility'])->name('admin.facilities.update');
        Route::delete('facilities/{facility}', [MasterDataController::class, 'destroyFacility'])->name('admin.facilities.destroy');

        Route::get('settings', [AdminManagementController::class, 'settings'])->name('admin.settings');
        Route::put('settings', [AdminManagementController::class, 'updateSettings'])->name('admin.settings.update');

        Route::get('users', [AdminManagementController::class, 'users'])->name('admin.users');
        Route::post('users', [AdminManagementController::class, 'storeUser'])->name('admin.users.store');
        Route::put('users/{user}', [AdminManagementController::class, 'updateUser'])->name('admin.users.update');

        Route::get('access', [AccessManagementController::class, 'index'])->name('admin.access');
        Route::put('access/{role}', [AccessManagementController::class, 'update'])->name('admin.access.update');

        Route::get('penalties', [AdminManagementController::class, 'penalties'])->name('admin.penalties');
        Route::post('strikes', [AdminManagementController::class, 'storeStrike'])->name('admin.strikes.store');
        Route::post('strikes/{strike}/cancel', [AdminManagementController::class, 'cancelStrike'])->name('admin.strikes.cancel');
        Route::post('suspensions', [AdminManagementController::class, 'storeSuspension'])->name('admin.suspensions.store');
        Route::post('suspensions/{suspension}/end', [AdminManagementController::class, 'endSuspension'])->name('admin.suspensions.end');

        Route::get('bookings', [AdminManagementController::class, 'bookings'])->name('admin.bookings');
        Route::get('loans', [AdminManagementController::class, 'loans'])->name('admin.loans');
        Route::post('loans/{loan}/handover', [AdminManagementController::class, 'handoverLoan'])->name('admin.loans.handover');
        Route::post('loans/{loan}/return', [AdminManagementController::class, 'returnLoan'])->name('admin.loans.return');

        Route::get('reports', [AdminManagementController::class, 'reports'])->name('admin.reports');
    });
});
