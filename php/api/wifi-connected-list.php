<?php
declare(strict_types=1);

/**
 * Paginated list of wifi_sessions for the admin "Connected User" view.
 * POST JSON: { "page": 1, "pageSize": 10 }
 */

header('Content-Type: application/json; charset=utf-8');

$allowedOrigin = getenv('WIFI_CONNECTED_CORS_ORIGIN') ?: getenv('LOGIN_CORS_ORIGIN') ?: '*';
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

$page = isset($body['page']) ? (int) $body['page'] : 1;
$pageSize = isset($body['pageSize']) ? (int) $body['pageSize'] : 10;
if ($page < 1) {
    $page = 1;
}
if ($pageSize < 1) {
    $pageSize = 10;
}
if ($pageSize > 100) {
    $pageSize = 100;
}
$offset = ($page - 1) * $pageSize;

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

try {
    $totalStmt = $pdo->query('SELECT COUNT(*) AS c FROM wifi_sessions');
    $totalRow = $totalStmt->fetch();
    $total = (int) ($totalRow['c'] ?? 0);

    $sql = 'SELECT id,
        phone,
        COALESCE(NULLIF(TRIM(device), \'\'), LEFT(COALESCE(user_agent, \'\'), 80)) AS device,
        ssid,
        TIMESTAMPDIFF(SECOND, connected_at, IFNULL(disconnected_at, UTC_TIMESTAMP())) AS online_seconds,
        (disconnected_at IS NULL AND last_seen_at >= (UTC_TIMESTAMP() - INTERVAL 5 MINUTE)) AS is_live
      FROM wifi_sessions
      ORDER BY last_seen_at DESC
      LIMIT :lim OFFSET :off';

    $st = $pdo->prepare($sql);
    $st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
    $st->bindValue(':off', $offset, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Query failed. Did you run php/sql/wifi_sessions.sql?']);
    exit;
}

$out = [];
foreach ($rows as $r) {
    $out[] = [
        'id' => (int) $r['id'],
        'phone' => $r['phone'] !== null ? (string) $r['phone'] : '',
        'device' => (string) ($r['device'] ?? ''),
        'ssid' => $r['ssid'] !== null ? (string) $r['ssid'] : '',
        'onlineSeconds' => (int) ($r['online_seconds'] ?? 0),
        'isLive' => ((int) ($r['is_live'] ?? 0)) === 1,
    ];
}

echo json_encode(
    [
        'ok' => true,
        'rows' => $out,
        'total' => $total,
        'page' => $page,
        'pageSize' => $pageSize,
    ],
    JSON_UNESCAPED_SLASHES
);
