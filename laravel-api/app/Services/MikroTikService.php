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
     * Return all currently active hotspot sessions from MikroTik.
     *
     * @return array{sessions: array, error: string|null, host: string}
     */
    public function getActiveSessions(): array
    {
        $result   = ['sessions' => [], 'error' => null, 'host' => $this->host];
        $sessions = $this->fetchActiveSessions($result);
        $result['sessions'] = $sessions;
        return $result;
    }

    /**
     * Create or update a per-user hotspot account on MikroTik.
     *
     * Using the phone number as the username gives every user their own
     * tracked session instead of sharing a single "guest" account.
     *
     * @param  string  $username       Phone in E.164 format (e.g. "255712345678")
     * @param  string  $password       Plain-text password to set on MikroTik
     * @param  int     $sessionMinutes Session time limit to enforce (e.g. 480)
     * @return array{error: string|null, host: string}
     */
    public function createOrUpdateHotspotUser(string $username, string $password, int $sessionMinutes): array
    {
        $result = ['error' => null, 'host' => $this->host];

        // Look for an existing hotspot user with this name
        $dummy = [];
        $users = $this->request('GET', '/rest/ip/hotspot/user', $dummy);
        $existingId = null;
        if (is_array($users)) {
            foreach ($users as $u) {
                if (($u['name'] ?? '') === $username) {
                    $existingId = $u['.id'] ?? null;
                    break;
                }
            }
        }

        $uptime  = $this->minutesToUptimeString($sessionMinutes);
        $payload = json_encode([
            'name'         => $username,
            'password'     => $password,
            'limit-uptime' => $uptime,
        ]);

        if ($existingId !== null) {
            // Update existing user (PATCH)
            $this->requestWithBody('PATCH', '/rest/ip/hotspot/user/' . $existingId, $payload, $result);
        } else {
            // Create new user (PUT)
            $this->requestWithBody('PUT', '/rest/ip/hotspot/user', $payload, $result);
        }

        return $result;
    }

    /**
     * Remove a hotspot user account by username (phone number).
     */
    public function removeHotspotUser(string $username): void
    {
        $dummy = [];
        $users = $this->request('GET', '/rest/ip/hotspot/user', $dummy);
        if (!is_array($users)) {
            return;
        }

        foreach ($users as $u) {
            if (($u['name'] ?? '') === $username) {
                $id = $u['.id'] ?? null;
                if ($id) {
                    $this->request('DELETE', '/rest/ip/hotspot/user/' . $id);
                }
            }
        }
    }

    // ---------------------------------------------------------------
    // Session format helper
    // ---------------------------------------------------------------

    /** Convert minutes to MikroTik uptime string "HH:MM:SS". */
    private function minutesToUptimeString(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d:00', $h, $m);
    }

    // ---------------------------------------------------------------

    /**
     * Remove all active HotSpot sessions for the given MAC address.
     *
     * @param  string  $mac  e.g. "AA:BB:CC:DD:EE:FF"
     * @return array  ['kicked' => int, 'error' => string|null, 'host' => string]
     */
    public function kickHotspotSession(string $mac): array
    {
        $result = ['kicked' => 0, 'error' => null, 'host' => $this->host];

        $sessions = $this->fetchActiveSessions($result);
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

    private function fetchActiveSessions(array &$result = []): array
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
    private function requestWithBody(string $method, string $path, string $jsonBody, array &$result = []): mixed
    {
        $url = 'http://' . $this->host . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_USERPWD        => $this->user . ':' . $this->password,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
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
        if ($status >= 400) {
            $result['error'] = "MikroTik returned HTTP {$status}: {$body}";
            return null;
        }

        return json_decode($body, true);
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
