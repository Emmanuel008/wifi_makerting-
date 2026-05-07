<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class StoreWifiPasswordController extends BaseApiController
{
    public function __invoke(Request $request)
    {
        $body = $this->decodeJsonBody($request->getContent());
        if ($body === null) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Invalid JSON body'], 400),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        $wifiPassword = isset($body['wifiPassword']) ? trim((string) $body['wifiPassword']) : '';
        if ($wifiPassword === '') {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'wifiPassword is required'], 400),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        $passwordHash = password_hash($wifiPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Could not hash WiFi password'], 500),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        try {
            DB::table('wifi_passwords')->updateOrInsert(
                ['id' => 1],
                ['password_hash' => $passwordHash, 'updated_at' => now()]
            );
        } catch (Throwable) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Could not save password. Run migrations first.'], 500),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        return $this->withCors(
            response()->json(['ok' => true], 200, [], JSON_UNESCAPED_SLASHES),
            'CAPTIVE_CORS_ORIGIN'
        );
    }
}
