<?php
declare(strict_types=1);

/**
 * Captive portal: guest submits phone + venue WiFi portal password.
 * Configure CAPTIVE_WIFI_PASSWORD env or captive_wifi_password in db-credentials.php.
 * Router still authorizes the session; this stores the lead and returns optional redirect URL.
 */

header('Content-Type: application/json; charset=utf-8');

$allowedOrigin = getenv('CAPTIVE_CORS_ORIGIN') ?: '*';
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

function captive_normalize_phone(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', trim($raw));
    if ($digits === null || $digits === '' || strlen($digits) < 9) {
        return null;
    }
    if (strlen($digits) === 9 && preg_match('/^[67]/', $digits)) {
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

function captive_sanitize_mac(?string $mac): ?string
{
    if ($mac === null || $mac === '') {
        return null;
    }
    $t = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');
    if (strlen($t) !== 12) {
        return null;
    }
    return implode(':', str_split($t, 2));
}

function captive_sanitize_dst(?string $dst): ?string
{
    if ($dst === null || $dst === '') {
        return null;
    }
    $t = trim($dst);
    if (strlen($t) > 2048) {
        return null;
    }
    if (preg_match('#^https?://#i', $t)) {
        return $t;
    }
    return null;
}

$credentialsPath = dirname(__DIR__) . '/config/db-credentials.php';
if (!is_readable($credentialsPath)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database not configured. Copy db-credentials.example.php to db-credentials.php.',
    ]);
    exit;
}

/** @var array{host?: string, port?: int, database?: string, username?: string, password?: string, captive_wifi_password?: string} $dbCfg */
$dbCfg = require $credentialsPath;
$host = trim((string) ($dbCfg['host'] ?? '127.0.0.1'));
$port = (int) ($dbCfg['port'] ?? 3306);
$database = trim((string) ($dbCfg['database'] ?? ''));
$dbUser = trim((string) ($dbCfg['username'] ?? ''));
$dbPass = (string) ($dbCfg['password'] ?? '');
if ($database === '' || $dbUser === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'database and username must be set in db-credentials.php']);
    exit;
}

$envPass = getenv('CAPTIVE_WIFI_PASSWORD');
$fromEnv = is_string($envPass) ? trim($envPass) : '';
$expected = $fromEnv !== '' ? $fromEnv : trim((string) ($dbCfg['captive_wifi_password'] ?? ''));
if ($expected === '') {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Captive WiFi password not configured. Set CAPTIVE_WIFI_PASSWORD or captive_wifi_password in db-credentials.php.',
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$phone = captive_normalize_phone(isset($body['phone']) ? (string) $body['phone'] : '');
if ($phone === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Enter a valid mobile number.']);
    exit;
}

$wifiPassword = isset($body['wifiPassword']) ? (string) $body['wifiPassword'] : '';
if ($wifiPassword === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'WiFi password is required.']);
    exit;
}

if (!hash_equals($expected, $wifiPassword)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Incorrect WiFi password.']);
    exit;
}

require_once dirname(__DIR__) . '/lib/wifi-session-helpers.php';

$mac = captive_sanitize_mac(isset($body['mac']) ? (string) $body['mac'] : null);
$dst = captive_sanitize_dst(isset($body['dst']) ? (string) $body['dst'] : null);
$clientIp = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';
$ssid = wifi_sanitize_ssid(isset($body['ssid']) ? (string) $body['ssid'] : null);
$device = wifi_sanitize_device_label(isset($body['device']) ? (string) $body['device'] : null);
$userAgent = wifi_clamp_user_agent(isset($body['userAgent']) ? (string) $body['userAgent'] : null);

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    exit;
}

$now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO wifi_guest_leads (phone, name, mac, client_ip, original_url, terms_accepted_at, verified_at)
         VALUES (:phone, NULL, :mac, :client_ip, :original_url, :terms_accepted_at, :verified_at)'
    )->execute([
        'phone' => $phone,
        'mac' => $mac,
        'client_ip' => $clientIp !== '' ? $clientIp : null,
        'original_url' => $dst,
        'terms_accepted_at' => $now,
        'verified_at' => $now,
    ]);
    wifi_session_ping($pdo, $now, [
        'phone' => $phone,
        'mac' => $mac,
        'ssid' => $ssid,
        'device' => $device,
        'user_agent' => $userAgent,
        'client_ip' => $clientIp !== '' ? $clientIp : null,
    ]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Could not save registration. Run php/sql/wifi_captive.sql and php/sql/wifi_sessions.sql?',
    ]);
    exit;
}

echo json_encode(
    [
        'ok' => true,
        'destination' => $dst,
    ],
    JSON_UNESCAPED_SLASHES
);
