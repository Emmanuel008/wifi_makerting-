<?php
declare(strict_types=1);

function wifi_sanitize_ssid(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $t = trim($raw);
    if ($t === '' || strlen($t) > 128) {
        return null;
    }
    return $t;
}

function wifi_sanitize_device_label(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $t = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');
    if ($t === '') {
        return null;
    }
    return strlen($t) > 255 ? substr($t, 0, 255) : $t;
}

function wifi_clamp_user_agent(?string $ua): ?string
{
    if ($ua === null) {
        return null;
    }
    $t = trim($ua);
    if ($t === '') {
        return null;
    }
    return strlen($t) > 2000 ? substr($t, 0, 2000) : $t;
}

/**
 * @return array<string, mixed>|null
 */
function wifi_session_find_open(PDO $pdo, ?string $mac, ?string $phone): ?array
{
    if ($mac !== null && $mac !== '') {
        $st = $pdo->prepare(
            'SELECT * FROM wifi_sessions WHERE mac = :mac AND disconnected_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $st->execute(['mac' => $mac]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
    if ($phone !== null && $phone !== '') {
        $st = $pdo->prepare(
            'SELECT * FROM wifi_sessions WHERE phone = :phone AND mac IS NULL AND disconnected_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $st->execute(['phone' => $phone]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }
    return null;
}

/**
 * Upsert an active WiFi session (heartbeat / captive login).
 *
 * @param array{phone?: string|null, mac?: string|null, ssid?: string|null, device?: string|null, user_agent?: string|null, client_ip?: string|null} $fields
 */
function wifi_session_ping(PDO $pdo, string $now, array $fields): void
{
    $phone = isset($fields['phone']) && $fields['phone'] !== '' ? (string) $fields['phone'] : null;
    $mac = isset($fields['mac']) && $fields['mac'] !== '' ? (string) $fields['mac'] : null;
    $ssid = array_key_exists('ssid', $fields) ? $fields['ssid'] : null;
    $device = array_key_exists('device', $fields) ? $fields['device'] : null;
    $ua = array_key_exists('user_agent', $fields) ? $fields['user_agent'] : null;
    $ip = array_key_exists('client_ip', $fields) ? $fields['client_ip'] : null;

    if (($mac === null || $mac === '') && ($phone === null || $phone === '')) {
        throw new InvalidArgumentException('mac or phone required');
    }

    $row = wifi_session_find_open($pdo, $mac, $phone);
    if ($row) {
        $id = (int) $row['id'];
        $upd = $pdo->prepare(
            'UPDATE wifi_sessions SET
                last_seen_at = :now,
                phone = COALESCE(:phone, phone),
                mac = COALESCE(:mac, mac),
                ssid = COALESCE(:ssid, ssid),
                device = COALESCE(:device, device),
                user_agent = COALESCE(:ua, user_agent),
                client_ip = COALESCE(:ip, client_ip)
             WHERE id = :id'
        );
        $upd->execute([
            'now' => $now,
            'phone' => $phone,
            'mac' => $mac,
            'ssid' => $ssid,
            'device' => $device,
            'ua' => $ua,
            'ip' => $ip,
            'id' => $id,
        ]);
        return;
    }

    $ins = $pdo->prepare(
        'INSERT INTO wifi_sessions (phone, mac, device, user_agent, ssid, client_ip, connected_at, last_seen_at)
         VALUES (:phone, :mac, :device, :ua, :ssid, :ip, :c0, :c1)'
    );
    $ins->execute([
        'phone' => $phone,
        'mac' => $mac,
        'device' => $device,
        'ua' => $ua,
        'ssid' => $ssid,
        'ip' => $ip,
        'c0' => $now,
        'c1' => $now,
    ]);
}

function wifi_session_disconnect(PDO $pdo, string $now, ?string $mac, ?string $phone): int
{
    if ($mac !== null && $mac !== '') {
        $st = $pdo->prepare(
            'UPDATE wifi_sessions SET disconnected_at = :now WHERE mac = :mac AND disconnected_at IS NULL'
        );
        $st->execute(['now' => $now, 'mac' => $mac]);
        return $st->rowCount();
    }
    if ($phone !== null && $phone !== '') {
        $st = $pdo->prepare(
            'UPDATE wifi_sessions SET disconnected_at = :now WHERE phone = :phone AND disconnected_at IS NULL'
        );
        $st->execute(['now' => $now, 'phone' => $phone]);
        return $st->rowCount();
    }
    return 0;
}
