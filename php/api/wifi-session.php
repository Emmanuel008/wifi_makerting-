<?php
declare(strict_types=1);

/**
 * Heartbeat / disconnect for wifi_sessions (router scripts or captive page).
 * POST JSON: { "action": "ping"|"disconnect", "mac": "...", "phone": "+...", "ssid": "...", "device": "...", "userAgent": "..." }
 * At least one of mac or phone is required for ping; disconnect prefers mac, else phone.
 */

header('Content-Type: application/json; charset=utf-8');

$allowedOrigin = getenv('WIFI_SESSION_CORS_ORIGIN') ?: getenv('CAPTIVE_CORS_ORIGIN') ?: '*';
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

require_once dirname(__DIR__) . '/lib/wifi-session-helpers.php';

$credentialsPath = dirname(__DIR__) . '/config/db-credentials.php';
if (!is_readable($credentialsPath)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Database not configured. Copy db-credentials.example.php to db-credentials.php.',
    ]);
    exit;
}

/** @var array{host?: string, port?: int, database?: string, username?: string, password?: string} $dbCfg */
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

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : '';
if ($action !== 'ping' && $action !== 'disconnect') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'action must be ping or disconnect']);
    exit;
}

$macRaw = isset($body['mac']) ? (string) $body['mac'] : '';
$mac = null;
if ($macRaw !== '') {
    $t = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $macRaw) ?? '');
    if (strlen($t) === 12) {
        $mac = implode(':', str_split($t, 2));
    }
}

$phone = isset($body['phone']) ? trim((string) $body['phone']) : '';
$phone = $phone !== '' ? $phone : null;

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
$clientIp = isset($_SERVER['REMOTE_ADDR']) ? trim((string) $_SERVER['REMOTE_ADDR']) : '';

if ($action === 'disconnect') {
    if (($mac === null || $mac === '') && ($phone === null || $phone === '')) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'mac or phone required for disconnect']);
        exit;
    }
    try {
        $n = wifi_session_disconnect($pdo, $now, $mac, $phone);
        echo json_encode(['ok' => true, 'closed' => $n], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not update session. Run php/sql/wifi_sessions.sql?']);
    }
    exit;
}

$ssid = wifi_sanitize_ssid(isset($body['ssid']) ? (string) $body['ssid'] : null);
$device = wifi_sanitize_device_label(isset($body['device']) ? (string) $body['device'] : null);
$ua = wifi_clamp_user_agent(isset($body['userAgent']) ? (string) $body['userAgent'] : null);

try {
    wifi_session_ping($pdo, $now, [
        'phone' => $phone,
        'mac' => $mac,
        'ssid' => $ssid,
        'device' => $device,
        'user_agent' => $ua,
        'client_ip' => $clientIp !== '' ? $clientIp : null,
    ]);
    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save session. Run php/sql/wifi_sessions.sql?']);
}
exit;
