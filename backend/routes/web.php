<?php

use App\Http\Controllers\ChamaMembershipController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
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
    });

    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
