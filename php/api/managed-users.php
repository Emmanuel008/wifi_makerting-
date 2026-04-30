<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$allowedOrigin = getenv('MANAGED_USERS_CORS_ORIGIN') ?: getenv('LOGIN_CORS_ORIGIN') ?: '*';
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

$action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : '';
$allowedActions = ['list', 'save', 'update', 'delete'];
if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'action is required: list, save, update, or delete',
    ]);
    exit;
}

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

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function normalize_phone(string $phone): string
{
    $p = trim($phone);
    if ($p === '') {
        return '';
    }
    if ($p[0] === '+') {
        return preg_replace('/[^\d+]/', '', $p) ?? '';
    }
    $digits = preg_replace('/\D/', '', $p) ?? '';
    return $digits !== '' ? '+' . $digits : '';
}

function valid_role(mixed $role): ?string
{
    $r = is_string($role) ? strtolower(trim($role)) : '';
    if ($r === 'admin' || $r === 'business') {
        return $r;
    }
    return null;
}

/** Default password for newly created users (User Management → Save). */
function default_new_user_password_hash(): string
{
    return password_hash('admin', PASSWORD_DEFAULT);
}

if ($action === 'list') {
    $perPage = isset($body['perPage']) ? (int) $body['perPage'] : 10;
    if ($perPage < 1) {
        $perPage = 10;
    }
    if ($perPage > 100) {
        $perPage = 100;
    }
    $page = isset($body['page']) ? (int) $body['page'] : 1;
    if ($page < 1) {
        $page = 1;
    }

    $total = (int) $pdo->query('SELECT COUNT(*) FROM managed_users')->fetchColumn();
    $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        'SELECT id, name, company_name, email, phone, role,
                UNIX_TIMESTAMP(created_at) AS created_at,
                UNIX_TIMESTAMP(updated_at) AS updated_at
         FROM managed_users
         ORDER BY id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $users = [];
    foreach ($rows as $row) {
        $users[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'companyName' => (string) $row['company_name'],
            'email' => (string) $row['email'],
            'phone' => (string) $row['phone'],
            'role' => (string) $row['role'],
            'createdAt' => (int) $row['created_at'] * 1000,
            'updatedAt' => (int) $row['updated_at'] * 1000,
        ];
    }
    echo json_encode([
        'ok' => true,
        'users' => $users,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'delete') {
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id < 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'id is required for delete']);
        exit;
    }
    $stmt = $pdo->prepare('DELETE FROM managed_users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() < 1) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'User not found']);
        exit;
    }
    echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES);
    exit;
}

$name = isset($body['name']) ? trim((string) $body['name']) : '';
$companyName = isset($body['companyName']) ? trim((string) $body['companyName']) : '';
$email = normalize_email(isset($body['email']) ? (string) $body['email'] : '');
$phone = normalize_phone(isset($body['phone']) ? (string) $body['phone'] : '');
$role = valid_role($body['role'] ?? null);

if ($name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'name is required']);
    exit;
}
if ($companyName === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'companyName is required']);
    exit;
}
if ($email === '' || !str_contains($email, '@')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'valid email is required']);
    exit;
}
if ($phone === '' || strlen($phone) < 8) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'valid phone number is required']);
    exit;
}
if ($role === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'role must be admin or business']);
    exit;
}

if ($action === 'save') {
    $hash = default_new_user_password_hash();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO managed_users (name, company_name, email, phone, role, password_hash)
             VALUES (:name, :company_name, :email, :phone, :role, :password_hash)'
        );
        $stmt->execute([
            'name' => $name,
            'company_name' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'password_hash' => $hash,
        ]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Email or phone is already in use']);
            exit;
        }
        throw $e;
    }
    $newId = (int) $pdo->lastInsertId();
    echo json_encode([
        'ok' => true,
        'user' => [
            'id' => $newId,
            'name' => $name,
            'companyName' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($action === 'update') {
    $id = isset($body['id']) ? (int) $body['id'] : 0;
    if ($id < 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'id is required for update']);
        exit;
    }
    $exists = $pdo->prepare('SELECT id FROM managed_users WHERE id = :id');
    $exists->execute(['id' => $id]);
    if (!$exists->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'User not found']);
        exit;
    }
    try {
        $stmt = $pdo->prepare(
            'UPDATE managed_users
             SET name = :name, company_name = :company_name, email = :email, phone = :phone, role = :role
             WHERE id = :id'
        );
        $stmt->execute([
            'name' => $name,
            'company_name' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'id' => $id,
        ]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Email or phone is already in use']);
            exit;
        }
        throw $e;
    }
    echo json_encode([
        'ok' => true,
        'user' => [
            'id' => $id,
            'name' => $name,
            'companyName' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
        ],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(500);
echo json_encode(['ok' => false, 'error' => 'Unhandled action']);
