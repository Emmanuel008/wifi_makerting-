<?php

namespace App\Http\Controllers\Api;

use App\Support\WifiSessionService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class WifiSessionController extends BaseApiController
{
    public function __construct(private readonly WifiSessionService $wifiSessions)
    {
    }

    public function handleSession(Request $request)
    {
        $body = $this->decodeJsonBody($request->getContent());
        if ($body === null) {
            return response()->json(['sucess' => false, 'error' => 'Invalid JSON body'], 400);
        }

        $action = strtolower(trim((string) ($body['action'] ?? '')));
        if ($action !== 'ping' && $action !== 'disconnect') {
            return response()->json(['sucess' => false, 'error' => 'action must be ping or disconnect'], 400);
        }

        $macRaw = (string) ($body['mac'] ?? '');
        $mac = null;
        if ($macRaw !== '') {
            $clean = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $macRaw));
            if (strlen($clean) === 12) {
                $mac = implode(':', str_split($clean, 2));
            }
        }
        $phone = trim((string) ($body['phone'] ?? ''));
        $phone = $phone !== '' ? $phone : null;
        $now = now()->format('Y-m-d H:i:s');

        if ($action === 'disconnect') {
            if (($mac === null || $mac === '') && ($phone === null || $phone === '')) {
                return response()->json(['sucess' => false, 'error' => 'mac or phone required for disconnect'], 400);
            }

            try {
                $closed = $this->wifiSessions->disconnect($now, $mac, $phone);
            } catch (Throwable) {
                return response()->json(['sucess' => false, 'error' => 'Could not update session. Run php/sql/wifi_sessions.sql?'], 500);
            }

            return response()->json(['sucess' => true, 'closed' => $closed], 200, [], JSON_UNESCAPED_SLASHES);
        }

        $ssid = $this->wifiSessions->sanitizeSsid(isset($body['ssid']) ? (string) $body['ssid'] : null);
        $device = $this->wifiSessions->sanitizeDeviceLabel(isset($body['device']) ? (string) $body['device'] : null);
        $userAgent = $this->wifiSessions->clampUserAgent(isset($body['userAgent']) ? (string) $body['userAgent'] : null);
        $clientIp = trim((string) $request->ip());

        try {
            $this->wifiSessions->ping($now, [
                'phone' => $phone,
                'mac' => $mac,
                'ssid' => $ssid,
                'device' => $device,
                'user_agent' => $userAgent,
                'client_ip' => $clientIp !== '' ? $clientIp : null,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['sucess' => false, 'error' => $e->getMessage()], 400);
        } catch (Throwable) {
            return response()->json(['sucess' => false, 'error' => 'Could not save session. Run php/sql/wifi_sessions.sql?'], 500);
        }

        return response()->json(['sucess' => true], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
