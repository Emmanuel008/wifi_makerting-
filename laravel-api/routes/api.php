<?php

use App\Http\Controllers\Api\CaptiveRegisterController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\ManagedUsersController;
use App\Http\Controllers\Api\SendSmsController;
use App\Http\Controllers\Api\StoreWifiPasswordController;
use App\Http\Controllers\Api\WifiClientAuthController;
use App\Http\Controllers\Api\WifiConnectedListController;
use App\Http\Controllers\Api\WifiSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', LoginController::class);
Route::post('/managed-users', ManagedUsersController::class);
Route::post('/send-sms', SendSmsController::class);
Route::post('/captive-register', CaptiveRegisterController::class);
Route::post('/store-wifi-password', StoreWifiPasswordController::class);
Route::post('/wifi-client-auth', WifiClientAuthController::class);
Route::post('/wifi-connected-list', WifiConnectedListController::class);
Route::post('/wifi-session', WifiSessionController::class);

Route::options('/{any}', function (Request $request) {
    $origin = (string) $request->header('Origin', '*');

    return response('', 204)
        ->header('Access-Control-Allow-Origin', $origin !== '' ? $origin : '*')
        ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type');
})->where('any', '.*');
