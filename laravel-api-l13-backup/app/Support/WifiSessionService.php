<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WifiSessionService
{
    public function sanitizeSsid(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);
        if ($value === '' || strlen($value) > 128) {
            return null;
        }

        return $value;
    }

    public function sanitizeDeviceLabel(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $raw));
        if ($value === '') {
            return null;
        }

        return strlen($value) > 255 ? substr($value, 0, 255) : $value;
    }

    public function clampUserAgent(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        return strlen($value) > 2000 ? substr($value, 0, 2000) : $value;
    }

    public function ping(string $now, array $fields): void
    {
        $phone = isset($fields['phone']) && $fields['phone'] !== '' ? (string) $fields['phone'] : null;
        $mac = isset($fields['mac']) && $fields['mac'] !== '' ? (string) $fields['mac'] : null;
        $ssid = array_key_exists('ssid', $fields) ? $fields['ssid'] : null;
        $device = array_key_exists('device', $fields) ? $fields['device'] : null;
        $userAgent = array_key_exists('user_agent', $fields) ? $fields['user_agent'] : null;
        $clientIp = array_key_exists('client_ip', $fields) ? $fields['client_ip'] : null;

        if (($mac === null || $mac === '') && ($phone === null || $phone === '')) {
            throw new InvalidArgumentException('mac or phone required');
        }

        $row = $this->findOpenSession($mac, $phone);
        if ($row !== null) {
            DB::table('wifi_sessions')
                ->where('id', $row->id)
                ->update([
                    'last_seen_at' => $now,
                    'phone' => $phone ?? $row->phone,
                    'mac' => $mac ?? $row->mac,
                    'ssid' => $ssid ?? $row->ssid,
                    'device' => $device ?? $row->device,
                    'user_agent' => $userAgent ?? $row->user_agent,
                    'client_ip' => $clientIp ?? $row->client_ip,
                ]);

            return;
        }

        DB::table('wifi_sessions')->insert([
            'phone' => $phone,
            'mac' => $mac,
            'device' => $device,
            'user_agent' => $userAgent,
            'ssid' => $ssid,
            'client_ip' => $clientIp,
            'connected_at' => $now,
            'last_seen_at' => $now,
        ]);
    }

    public function disconnect(string $now, ?string $mac, ?string $phone): int
    {
        if ($mac !== null && $mac !== '') {
            return DB::table('wifi_sessions')
                ->where('mac', $mac)
                ->whereNull('disconnected_at')
                ->update(['disconnected_at' => $now]);
        }

        if ($phone !== null && $phone !== '') {
            return DB::table('wifi_sessions')
                ->where('phone', $phone)
                ->whereNull('disconnected_at')
                ->update(['disconnected_at' => $now]);
        }

        return 0;
    }

    private function findOpenSession(?string $mac, ?string $phone): ?object
    {
        if ($mac !== null && $mac !== '') {
            return DB::table('wifi_sessions')
                ->where('mac', $mac)
                ->whereNull('disconnected_at')
                ->orderByDesc('id')
                ->first();
        }

        if ($phone !== null && $phone !== '') {
            return DB::table('wifi_sessions')
                ->where('phone', $phone)
                ->whereNull('mac')
                ->whereNull('disconnected_at')
                ->orderByDesc('id')
                ->first();
        }

        return null;
    }
}
