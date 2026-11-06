<?php

use App\Http\Controllers\Api\DarajaCallbackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned REST API consumed by the Flutter Android app and other API
| clients (Part 26 of the specification). Authenticated via Sanctum
| personal access tokens. Endpoints are added module-by-module as each
| phase's backend functionality lands — Phase 1 only exposes the
| authenticated user's own profile and chama memberships.
|
*/

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Unauthenticated by necessity — Safaricom sends no bearer token. Authenticity rests on the
// unguessable {token} segment, checked inside the controller (see its docblock). Outside the
// web/"tenant" middleware stack entirely, since there is no logged-in user on this request.
Route::post('/mpesa/callback/{organization}/{token}', [DarajaCallbackController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('api.mpesa.callback');
