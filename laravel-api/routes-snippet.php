<?php

// Add these routes to your Laravel routes/api.php file

use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\ClientsController;
use App\Http\Controllers\Api\DashboardStatsController;
use App\Http\Controllers\Api\WifiClientsController;
use App\Http\Controllers\Api\StoreWifiPasswordController;
use App\Http\Controllers\Api\SendSmsController;
use App\Http\Controllers\Api\WifiClientAuthController;
use App\Http\Controllers\Api\DeliveryCallbackController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::post('/login', [LoginController::class, 'login']);
Route::post('/wifi-client-auth', [WifiClientAuthController::class, 'authenticateClient']);
Route::post('/delivery-callback', [DeliveryCallbackController::class, 'handle']);

// Authenticated routes (Bearer token required — admin or client)
Route::get('/dashboard-stats', [DashboardStatsController::class, 'index']);
Route::get('/wifi-clients', [WifiClientsController::class, 'index']);
Route::patch('/wifi-clients/{id}', [WifiClientsController::class, 'update']);
Route::delete('/wifi-clients/{id}', [WifiClientsController::class, 'destroy']);
Route::post('/send-sms', [SendSmsController::class, 'send']);

// Admin-only routes (Bearer token required — admin only, enforced inside controller)
Route::get('/clients', [ClientsController::class, 'index']);
Route::post('/clients', [ClientsController::class, 'store']);
Route::patch('/clients/{id}', [ClientsController::class, 'update']);
Route::delete('/clients/{id}', [ClientsController::class, 'destroy']);
Route::post('/wifi-clients/sync-mikrotik', [WifiClientsController::class, 'syncWithMikrotik']);

// Admin-only: WiFi password management
Route::post('/store-wifi-password', [StoreWifiPasswordController::class, 'store']);
