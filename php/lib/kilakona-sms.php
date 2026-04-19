<?php
declare(strict_types=1);

/**
 * @return array{
 *   ok: bool,
 *   http_code: int,
 *   provider?: mixed,
 *   raw?: string,
 *   error?: string,
 *   detail?: string
 * }
 */
function kilakona_send_vendor_sms(
    string $apiKey,
    string $apiSecret,
    string $senderId,
    string $message,
    string $contacts,
    string $deliveryReportUrl = ''
): array {
    $payload = [
        'senderId' => $senderId,
        'messageType' => 'text',
        'message' => $message,
        'contacts' => $contacts,
    ];
    if ($deliveryReportUrl !== '') {
        $payload['deliveryReportUrl'] = $deliveryReportUrl;
    }

    $url = 'https://messaging.kilakona.co.tz/api/v1/vendor/message/send';
    $jsonBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if ($jsonBody === false) {
        return ['ok' => false, 'http_code' => 500, 'error' => 'Failed to encode request'];
    }

    $headers = [
        'Content-Type: application/json',
        'api_key: ' . $apiKey,
        'api_secret: ' . $apiSecret,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'http_code' => 502,
            'error' => 'Upstream request failed',
            'detail' => $curlErr,
        ];
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'ok' => false,
            'http_code' => $httpCode >= 400 ? $httpCode : 502,
            'error' => 'Non-JSON response from SMS provider',
            'raw' => $response,
        ];
    }

    $ok = $httpCode >= 200 && $httpCode < 300;
    return [
        'ok' => $ok,
        'http_code' => $httpCode >= 100 && $httpCode < 600 ? $httpCode : 200,
        'provider' => $decoded,
    ];
}
