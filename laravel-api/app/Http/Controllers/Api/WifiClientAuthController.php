<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class WifiClientAuthController extends BaseApiController
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

        $phone = $this->normalizePhone((string) ($body['phone'] ?? ''));
        if ($phone === null) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Enter a valid mobile number.'], 400),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        $wifiPassword = isset($body['wifiPassword']) ? (string) $body['wifiPassword'] : '';
        if ($wifiPassword === '') {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'wifiPassword is required'], 400),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        try {
            $passwordRow = DB::table('wifi_passwords')
                ->select(['password_hash'])
                ->where('id', 1)
                ->first();
        } catch (Throwable) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Could not read WiFi password. Run migrations first.'], 500),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        if ($passwordRow === null || !password_verify($wifiPassword, (string) $passwordRow->password_hash)) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Incorrect WiFi password.'], 401),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        try {
            DB::table('wifi_clients')->updateOrInsert(
                ['phone' => $phone],
                ['updated_at' => now()]
            );
        } catch (Throwable) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Could not save WiFi client.'], 500),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        return $this->withCors(
            response()->json(['ok' => true, 'phone' => $phone], 200, [], JSON_UNESCAPED_SLASHES),
            'CAPTIVE_CORS_ORIGIN'
        );
    }

    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($raw));
        if ($digits === null || $digits === '' || strlen($digits) < 9) {
            return null;
        }

        if (strlen($digits) === 9 && preg_match('/^[67]/', $digits) === 1) {
            return '+255' . $digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '+255' . substr($digits, 1);
        }

        if (strlen($digits) >= 11 && strlen($digits) <= 15) {
            return '+' . $digits;
        }

        return null;
    }
}
