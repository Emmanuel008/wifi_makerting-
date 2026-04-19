<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$allowedOrigin = getenv('LOGIN_CORS_ORIGIN') ?: '*';
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
$username = trim((string) ($dbCfg['username'] ?? ''));
$password = (string) ($dbCfg['password'] ?? '');
if ($database === '' || $username === '') {
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

$email = isset($body['email']) ? strtolower(trim((string) $body['email'])) : '';
$plainPassword = isset($body['password']) ? (string) $body['password'] : '';
if ($email === '' || !str_contains($email, '@') || $plainPassword === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'email and password are required']);
    exit;
}

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);
try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, email, name, role, password_hash FROM managed_users WHERE email = :email LIMIT 1'
);
$stmt->execute(['email' => $email]);
$row = $stmt->fetch();
if (!$row || !password_verify($plainPassword, (string) $row['password_hash'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
    exit;
}

$r = (string) ($row['role'] ?? 'business');
if ($r !== 'admin' && $r !== 'business') {
    $r = 'business';
}

echo json_encode([
    'ok' => true,
    'user' => [
        'id' => (int) $row['id'],
        'email' => (string) $row['email'],
        'name' => (string) ($row['name'] ?? ''),
        'role' => $r,
    ],
], JSON_UNESCAPED_SLASHES);
