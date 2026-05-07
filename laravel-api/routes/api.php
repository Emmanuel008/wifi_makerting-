<?php

use App\Http\Controllers\Api\CaptiveRegisterController;
use App\Http\Controllers\Api\DeliveryCallbackController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\ManagedUsersController;
use App\Http\Controllers\Api\SendSmsController;
use App\Http\Controllers\Api\StoreWifiPasswordController;
use App\Http\Controllers\Api\WifiClientAuthController;
use App\Http\Controllers\Api\WifiConnectedListController;
use App\Http\Controllers\Api\WifiSessionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [LoginController::class, 'login']);
Route::post('/managed-users', [ManagedUsersController::class, 'handle']);
Route::post('/send-sms', [SendSmsController::class, 'send']);
Route::post('/delivery-callback', [DeliveryCallbackController::class, 'receive']);
Route::post('/captive-register', [CaptiveRegisterController::class, 'register']);
Route::post('/store-wifi-password', [StoreWifiPasswordController::class, 'storePassword']);
Route::post('/wifi-client-auth', [WifiClientAuthController::class, 'authenticateClient']);
Route::post('/wifi-connected-list', [WifiConnectedListController::class, 'list']);
Route::post('/wifi-session', [WifiSessionController::class, 'handleSession']);
