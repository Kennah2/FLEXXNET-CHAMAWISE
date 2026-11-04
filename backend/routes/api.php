<?php

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
