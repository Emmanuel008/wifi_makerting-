<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$allowedOrigin = getenv('SMS_CORS_ORIGIN') ?: '*';
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

$credentialsPath = dirname(__DIR__) . '/config/sms-credentials.php';
if (!is_readable($credentialsPath)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'SMS credentials not configured. Copy sms-credentials.example.php to sms-credentials.php.',
    ]);
    exit;
}

/** @var array{api_key?: string, api_secret?: string} $creds */
$creds = require $credentialsPath;
$apiKey = trim((string) ($creds['api_key'] ?? ''));
$apiSecret = trim((string) ($creds['api_secret'] ?? ''));
if ($apiKey === '' || $apiSecret === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'api_key and api_secret must be set in sms-credentials.php']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$senderId = isset($body['senderId']) ? trim((string) $body['senderId']) : '';
$message = isset($body['message']) ? trim((string) $body['message']) : '';
$contacts = isset($body['contacts']) ? trim((string) $body['contacts']) : '';
$deliveryReportUrl = isset($body['deliveryReportUrl']) ? trim((string) $body['deliveryReportUrl']) : '';

if ($senderId === '' || $message === '' || $contacts === '') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'senderId, message, and contacts are required',
    ]);
    exit;
}

require_once dirname(__DIR__) . '/lib/kilakona-sms.php';

$result = kilakona_send_vendor_sms($apiKey, $apiSecret, $senderId, $message, $contacts, $deliveryReportUrl);

if (isset($result['detail'])) {
    http_response_code((int) ($result['http_code'] ?? 502));
    echo json_encode([
        'ok' => false,
        'error' => $result['error'] ?? 'Upstream request failed',
        'detail' => $result['detail'],
    ]);
    exit;
}

if (isset($result['raw'])) {
    $hc = (int) ($result['http_code'] ?? 502);
    http_response_code($hc >= 100 && $hc < 600 ? $hc : 502);
    echo json_encode([
        'ok' => false,
        'error' => $result['error'] ?? 'Non-JSON response from SMS provider',
        'raw' => $result['raw'],
    ]);
    exit;
}

$httpCode = (int) ($result['http_code'] ?? 200);
http_response_code($httpCode >= 100 && $httpCode < 600 ? $httpCode : 200);
$decoded = $result['provider'] ?? null;
echo json_encode(['ok' => (bool) ($result['ok'] ?? false), 'provider' => $decoded], JSON_UNESCAPED_SLASHES);
