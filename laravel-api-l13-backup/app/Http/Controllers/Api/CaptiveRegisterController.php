<?php

namespace App\Http\Controllers\Api;

use App\Support\WifiSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CaptiveRegisterController extends BaseApiController
{
    public function __construct(private readonly WifiSessionService $wifiSessions)
    {
    }

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

        $wifiPassword = (string) ($body['wifiPassword'] ?? '');
        if ($wifiPassword === '') {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'WiFi password is required.'], 400),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        $expected = trim((string) env('CAPTIVE_WIFI_PASSWORD', ''));
        if ($expected === '') {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Captive WiFi password not configured. Set CAPTIVE_WIFI_PASSWORD.'], 500),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        if (!hash_equals($expected, $wifiPassword)) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Incorrect WiFi password.'], 401),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        $mac = $this->sanitizeMac(isset($body['mac']) ? (string) $body['mac'] : null);
        $dst = $this->sanitizeDst(isset($body['dst']) ? (string) $body['dst'] : null);
        $clientIp = trim((string) $request->ip());
        $ssid = $this->wifiSessions->sanitizeSsid(isset($body['ssid']) ? (string) $body['ssid'] : null);
        $device = $this->wifiSessions->sanitizeDeviceLabel(isset($body['device']) ? (string) $body['device'] : null);
        $userAgent = $this->wifiSessions->clampUserAgent(isset($body['userAgent']) ? (string) $body['userAgent'] : null);
        $now = now()->format('Y-m-d H:i:s');

        try {
            DB::transaction(function () use ($phone, $mac, $clientIp, $dst, $now, $ssid, $device, $userAgent): void {
                DB::table('wifi_guest_leads')->insert([
                    'phone' => $phone,
                    'name' => null,
                    'mac' => $mac,
                    'client_ip' => $clientIp !== '' ? $clientIp : null,
                    'original_url' => $dst,
                    'terms_accepted_at' => $now,
                    'verified_at' => $now,
                ]);

                $this->wifiSessions->ping($now, [
                    'phone' => $phone,
                    'mac' => $mac,
                    'ssid' => $ssid,
                    'device' => $device,
                    'user_agent' => $userAgent,
                    'client_ip' => $clientIp !== '' ? $clientIp : null,
                ]);
            });
        } catch (Throwable) {
            return $this->withCors(
                response()->json([
                    'ok' => false,
                    'error' => 'Could not save registration. Run php/sql/wifi_captive.sql and php/sql/wifi_sessions.sql?',
                ], 500),
                'CAPTIVE_CORS_ORIGIN'
            );
        }

        return $this->withCors(
            response()->json(['ok' => true, 'destination' => $dst], 200, [], JSON_UNESCAPED_SLASHES),
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

    private function sanitizeMac(?string $mac): ?string
    {
        if ($mac === null || $mac === '') {
            return null;
        }

        $value = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $mac));
        if (strlen($value) !== 12) {
            return null;
        }

        return implode(':', str_split($value, 2));
    }

    private function sanitizeDst(?string $dst): ?string
    {
        if ($dst === null || $dst === '') {
            return null;
        }

        $value = trim($dst);
        if (strlen($value) > 2048) {
            return null;
        }

        return preg_match('#^https?://#i', $value) === 1 ? $value : null;
    }
}
