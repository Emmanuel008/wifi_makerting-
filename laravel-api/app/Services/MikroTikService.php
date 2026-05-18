<?php

namespace App\Services;

/**
 * Thin wrapper around the MikroTik RouterOS REST API (RouterOS 7+).
 * Env vars required:
 *   MIKROTIK_HOST     – router IP reachable from THIS server (use WAN IP, NOT 10.5.50.1 which is unreachable from public internet)
 *   MIKROTIK_USER     – admin username (e.g. admin)
 *   MIKROTIK_PASSWORD – admin password
 */
class MikroTikService
{
    private string $host;
    private string $user;
    private string $password;

    public function __construct()
    {
        $this->host     = env('MIKROTIK_HOST', '10.5.50.1');
        $this->user     = env('MIKROTIK_USER', 'admin');
        $this->password = env('MIKROTIK_PASSWORD', '');
    }

    /**
     * Remove all active HotSpot sessions for the given MAC address.
     *
     * @param  string  $mac  e.g. "AA:BB:CC:DD:EE:FF"
     * @return array  ['kicked' => int, 'error' => string|null, 'host' => string]
     */
    public function kickHotspotSession(string $mac): array
    {
        $result = ['kicked' => 0, 'error' => null, 'host' => $this->host];

        $sessions = $this->getActiveSessions($result);
        if ($result['error'] !== null) {
            return $result;
        }

        foreach ($sessions as $session) {
            $sessionMac = strtoupper($session['mac-address'] ?? '');
            if ($sessionMac === strtoupper($mac)) {
                $id = $session['.id'] ?? null;
                if ($id) {
                    $this->removeSession($id);
                    $result['kicked']++;
                }
            }
        }

        // Remove mac-cookie — prevents MikroTik from silently re-authenticating
        // the device after kick using the stored cookie.
        $this->removeHotspotCookie($mac);

        return $result;
    }

    // ---------------------------------------------------------------

    private function getActiveSessions(array &$result = []): array
    {
        $response = $this->request('GET', '/rest/ip/hotspot/active', $result);
        if (!is_array($response)) {
            return [];
        }
        return $response;
    }

    private function removeSession(string $id): void
    {
        // RouterOS REST uses DELETE /rest/ip/hotspot/active/{.id}
        // The .id from RouterOS looks like "*A0532FD" — do NOT encode the *.
        $this->request('DELETE', '/rest/ip/hotspot/active/' . $id);
    }

    private function removeHotspotCookie(string $mac): void
    {
        // MikroTik stores a cookie per MAC to auto-login returning devices.
        // Removing it forces the device to go through the portal again.
        $dummy = [];
        $cookies = $this->request('GET', '/rest/ip/hotspot/cookie', $dummy);
        if (!is_array($cookies)) {
            return;
        }

        foreach ($cookies as $cookie) {
            $cookieMac = strtoupper($cookie['mac-address'] ?? '');
            if ($cookieMac === strtoupper($mac)) {
                $id = $cookie['.id'] ?? null;
                if ($id) {
                    $this->request('DELETE', '/rest/ip/hotspot/cookie/' . $id);
                }
            }
        }
    }

    private function releaseDhcpLease(string $mac): void
    {
        // DISABLED — deleting DHCP leases breaks hotspot traffic interception.
    }

    /**
     * @return array|null  Decoded JSON body, or null on failure.
     */
    private function request(string $method, string $path, array &$result = []): mixed
    {
        $url = 'http://' . $this->host . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_USERPWD        => $this->user . ':' . $this->password,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $body      = curl_exec($ch);
        $status    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $result['error'] = "cURL failed connecting to {$this->host}: {$curlError}";
            return null;
        }

        if ($status === 401) {
            $result['error'] = "MikroTik auth failed (HTTP 401) — check MIKROTIK_USER and MIKROTIK_PASSWORD in .env";
            return null;
        }

        if ($status >= 400 && $status !== 404) {
            $result['error'] = "MikroTik returned HTTP {$status}: {$body}";
            return null;
        }

        return json_decode($body, true);
    }
}
