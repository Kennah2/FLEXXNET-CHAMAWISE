<?php

use App\Http\Controllers\Api\DarajaCallbackController;
use App\Http\Controllers\Api\V1\AccountingController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChamaController;
use App\Http\Controllers\Api\V1\ContributionController;
use App\Http\Controllers\Api\V1\ContributionScheduleController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FinancialAccountController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\WithdrawalController;
use App\Http\Controllers\StkPushController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned REST API consumed by the Flutter Android app and other API
| clients (Part 26 of the specification). Authenticated via Sanctum
| personal access tokens. Every protected route re-runs the exact same
| policies and services the web app uses (see docs/API.md) — nothing here
| duplicates business or financial logic (Part 15).
|
*/

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/chamas', [ChamaController::class, 'index']);
        Route::get('/chamas/{organization}', [ChamaController::class, 'show']);

        Route::middleware('tenant')->prefix('chamas/{organization}')->group(function () {
            Route::get('/members', [MemberController::class, 'index']);

            Route::prefix('contributions')->group(function () {
                Route::get('/', [ContributionController::class, 'index']);
                Route::post('/', [ContributionController::class, 'store']);
                Route::post('/{contribution}/verify', [ContributionController::class, 'verify']);
                Route::post('/{contribution}/reject', [ContributionController::class, 'reject']);
                Route::post('/{contribution}/reverse', [ContributionController::class, 'reverse']);

                Route::post('/mpesa', [StkPushController::class, 'initiate']);
                Route::get('/mpesa/{transaction}', [StkPushController::class, 'status']);

                Route::get('/schedules', [ContributionScheduleController::class, 'index']);
                Route::get('/schedules/{schedule}', [ContributionScheduleController::class, 'show']);
            });

            Route::prefix('accounts')->group(function () {
                Route::get('/', [FinancialAccountController::class, 'index']);
                Route::get('/{account}', [FinancialAccountController::class, 'show']);
                Route::post('/', [FinancialAccountController::class, 'store']);
                Route::post('/{account}/reconcile', [FinancialAccountController::class, 'reconcile']);
            });

            Route::get('/transactions', [TransactionController::class, 'index']);
            Route::get('/accounting', [AccountingController::class, 'index']);

            Route::prefix('withdrawals')->group(function () {
                Route::get('/', [WithdrawalController::class, 'index']);
                Route::post('/', [WithdrawalController::class, 'store']);
                Route::post('/{withdrawal}/approve', [WithdrawalController::class, 'approve']);
                Route::post('/{withdrawal}/reject', [WithdrawalController::class, 'reject']);
                Route::post('/{withdrawal}/pay', [WithdrawalController::class, 'pay']);
                Route::post('/{withdrawal}/cancel', [WithdrawalController::class, 'cancel']);
                Route::post('/{withdrawal}/reverse', [WithdrawalController::class, 'reverse']);
            });

            Route::prefix('expenses')->group(function () {
                Route::get('/', [ExpenseController::class, 'index']);
                Route::post('/', [ExpenseController::class, 'store']);
                Route::post('/{expense}/reverse', [ExpenseController::class, 'reverse']);
            });
        });
    });
});

// Unauthenticated by necessity — Safaricom sends no bearer token. Authenticity rests on the
// unguessable {token} segment, checked inside the controller (see its docblock). Outside the
// web/"tenant" middleware stack entirely, since there is no logged-in user on this request.
Route::post('/mpesa/callback/{organization}/{token}', [DarajaCallbackController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('api.mpesa.callback');
