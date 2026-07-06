<?php

namespace App\Http\Controllers\Api;

use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class WifiClientAuthController extends BaseApiController
{
    public function authenticateClient(Request $request)
    {
        $phone = $this->normalizePhone((string) ($request->phone ?? ''));
        if ($phone === null) {
            return response()->json(['success' => false, 'error' => 'Enter a valid mobile number.'], 400);
        }

        $wifiPassword = (string) ($request->wifiPassword ?? '');
        if ($wifiPassword === '') {
            return response()->json(['success' => false, 'error' => 'wifiPassword is required'], 400);
        }

        try {
            $passwordRow = DB::table('wifi_passwords')
                ->select(['password_hash'])
                ->where('id', 1)
                ->first();
        } catch (Throwable) {
            return response()->json(['success' => false, 'error' => 'Could not read WiFi password. Run migrations first.'], 500);
        }

        if ($passwordRow === null || !hash_equals((string) $passwordRow->password_hash, $wifiPassword)) {
            return response()->json(['success' => false, 'error' => 'Incorrect WiFi password.'], 401);
        }

        $mac = trim((string) ($request->mac ?? ''));
        $ip  = trim((string) ($request->ip ?? ''));

        // Determine session minutes (existing client keeps their setting, new clients default to 480)
        $existingClient = DB::table('wifi_clients')->where('phone', $phone)->first();
        $sessionMinutes = $existingClient->session_minutes ?? 480;
        if (!$sessionMinutes || $sessionMinutes < 1) {
            $sessionMinutes = 480;
        }

        try {
            DB::table('wifi_clients')->updateOrInsert(
                ['phone' => $phone],
                [
                    'mac_address'        => $mac ?: null,
                    'ip_address'         => $ip ?: null,
                    'is_active'          => true,
                    'session_minutes'    => $sessionMinutes,
                    'session_started_at' => now(),
                    'updated_at'         => now(),
                ]
            );
        } catch (Throwable) {
            return response()->json(['success' => false, 'error' => 'Could not save WiFi client.'], 500);
        }

        // Generate a fresh one-time password for this user's MikroTik hotspot account.
        // We use the phone number as the MikroTik username so each user has their own
        // tracked session (instead of sharing a single "guest" account).
        $hotspotPassword = Str::random(16);

        try {
            (new MikroTikService())->createOrUpdateHotspotUser($phone, $hotspotPassword, $sessionMinutes);
        } catch (Throwable) {
            // MikroTik unreachable — still allow the client in;
            // they will need to use the fallback legacy guest password.
            $hotspotPassword = env('MIKROTIK_HOTSPOT_PASSWORD', '');
            $phone           = 'guest'; // signal to frontend to use legacy mode
        }

        return response()->json([
            'success'          => true,
            'phone'            => $this->normalizePhone((string) ($request->phone ?? '')),
            'hotspot_username' => $phone,
            'hotspot_password' => $hotspotPassword,
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($raw));
        if ($digits === null || $digits === '' || strlen($digits) < 9) {
            return null;
        }

        if (strlen($digits) === 9 && preg_match('/^[67]/', $digits) === 1) {
            return '255' . $digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '255' . substr($digits, 1);
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        return null;
    }
}
