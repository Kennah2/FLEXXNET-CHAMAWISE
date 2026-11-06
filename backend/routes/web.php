<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\ChamaMembershipController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\ContributionScheduleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialAccountController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\ModulePlaceholderController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PaymentGatewayCredentialController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StkPushController;
use App\Http\Controllers\WithdrawalController;
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

            Route::post('/mpesa', [StkPushController::class, 'initiate'])->name('mpesa.initiate');
            Route::get('/mpesa/{transaction}', [StkPushController::class, 'status'])->name('mpesa.status');
        });

        Route::prefix('payments/daraja')->name('organizations.payments.daraja.')->group(function () {
            Route::get('/', [PaymentGatewayCredentialController::class, 'edit'])->name('edit');
            Route::put('/', [PaymentGatewayCredentialController::class, 'update'])->name('update');
        });

        Route::prefix('accounts')->name('organizations.accounts.')->group(function () {
            Route::get('/', [FinancialAccountController::class, 'index'])->name('index');
            Route::get('/create', [FinancialAccountController::class, 'create'])->name('create');
            Route::post('/', [FinancialAccountController::class, 'store'])->name('store');
            Route::get('/{account}', [FinancialAccountController::class, 'show'])->name('show');
            Route::post('/{account}/reconcile', [FinancialAccountController::class, 'reconcile'])->name('reconcile');
        });

        Route::get('/transactions', [JournalEntryController::class, 'index'])->name('organizations.transactions.index');
        Route::get('/accounting', [AccountingController::class, 'index'])->name('organizations.accounting.index');

        Route::prefix('withdrawals')->name('organizations.withdrawals.')->group(function () {
            Route::get('/', [WithdrawalController::class, 'index'])->name('index');
            Route::post('/', [WithdrawalController::class, 'store'])->name('store');
            Route::post('/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('approve');
            Route::post('/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('reject');
            Route::post('/{withdrawal}/pay', [WithdrawalController::class, 'pay'])->name('pay');
            Route::post('/{withdrawal}/cancel', [WithdrawalController::class, 'cancel'])->name('cancel');
            Route::post('/{withdrawal}/reverse', [WithdrawalController::class, 'reverse'])->name('reverse');
        });

        Route::prefix('expenses')->name('organizations.expenses.')->group(function () {
            Route::get('/', [ExpenseController::class, 'index'])->name('index');
            Route::post('/', [ExpenseController::class, 'store'])->name('store');
            Route::post('/{expense}/reverse', [ExpenseController::class, 'reverse'])->name('reverse');
        });

        Route::get('/{module}', [ModulePlaceholderController::class, 'show'])
            ->whereIn('module', ['loans', 'reports', 'settings'])
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
