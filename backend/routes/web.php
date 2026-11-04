<?php

use App\Http\Controllers\ChamaMembershipController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\ContributionScheduleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ModulePlaceholderController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/chamas', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::get('/chamas/create', [OrganizationController::class, 'create'])->name('organizations.create');
    Route::post('/chamas', [OrganizationController::class, 'store'])->name('organizations.store');

    Route::middleware('tenant')->prefix('chamas/{organization}')->group(function () {
        Route::get('/', [OrganizationController::class, 'show'])->name('organizations.show');

        Route::get('/members', [ChamaMembershipController::class, 'index'])->name('organizations.members.index');
        Route::patch('/members/{membership}', [ChamaMembershipController::class, 'updateStatus'])->name('organizations.members.update-status');

        Route::post('/invitations', [InvitationController::class, 'store'])->name('organizations.invitations.store');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('organizations.invitations.destroy');

        Route::prefix('contributions')->name('organizations.contributions.')->group(function () {
            Route::get('/', [ContributionController::class, 'index'])->name('index');
            Route::post('/', [ContributionController::class, 'store'])->name('store');
            Route::post('/{contribution}/verify', [ContributionController::class, 'verify'])->name('verify');
            Route::post('/{contribution}/reject', [ContributionController::class, 'reject'])->name('reject');
            Route::post('/{contribution}/reverse', [ContributionController::class, 'reverse'])->name('reverse');

            Route::get('/schedules', [ContributionScheduleController::class, 'index'])->name('schedules.index');
            Route::get('/schedules/create', [ContributionScheduleController::class, 'create'])->name('schedules.create');
            Route::post('/schedules', [ContributionScheduleController::class, 'store'])->name('schedules.store');
            Route::get('/schedules/{schedule}', [ContributionScheduleController::class, 'show'])->name('schedules.show');
        });

        Route::get('/{module}', [ModulePlaceholderController::class, 'show'])
            ->whereIn('module', ['loans', 'accounts', 'transactions', 'reports', 'accounting', 'settings'])
            ->name('organizations.module.show');
    });

    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
