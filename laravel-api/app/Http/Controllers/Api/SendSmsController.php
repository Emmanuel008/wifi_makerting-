<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class SendSmsController extends BaseApiController
{
    public function __invoke(Request $request)
    {
        $body = $this->decodeJsonBody($request->getContent());
        if ($body === null) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Invalid JSON body'], 400),
                'SMS_CORS_ORIGIN'
            );
        }

        $senderId = trim((string) ($body['senderId'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));
        $contacts = trim((string) ($body['contacts'] ?? ''));
        $deliveryReportUrl = trim((string) ($body['deliveryReportUrl'] ?? ''));

        if ($senderId === '' || $message === '' || $contacts === '') {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'senderId, message, and contacts are required'], 400),
                'SMS_CORS_ORIGIN'
            );
        }

        $apiKey = trim((string) env('SMS_API_KEY', ''));
        $apiSecret = trim((string) env('SMS_API_SECRET', ''));

        if ($apiKey === '' || $apiSecret === '') {
            $legacyPath = base_path('../php/config/sms-credentials.php');
            if (is_readable($legacyPath)) {
                /** @var array{api_key?: string, api_secret?: string} $legacy */
                $legacy = require $legacyPath;
                $apiKey = $apiKey !== '' ? $apiKey : trim((string) ($legacy['api_key'] ?? ''));
                $apiSecret = $apiSecret !== '' ? $apiSecret : trim((string) ($legacy['api_secret'] ?? ''));
            }
        }

        if ($apiKey === '' || $apiSecret === '') {
            return $this->withCors(
                response()->json([
                    'ok' => false,
                    'error' => 'Set SMS_API_KEY and SMS_API_SECRET in laravel-api/.env (or provide php/config/sms-credentials.php).',
                ], 500),
                'SMS_CORS_ORIGIN'
            );
        }

        $payload = [
            'senderId' => $senderId,
            'messageType' => 'text',
            'message' => $message,
            'contacts' => $contacts,
        ];
        if ($deliveryReportUrl !== '') {
            $payload['deliveryReportUrl'] = $deliveryReportUrl;
        }

        try {
            $response = Http::withHeaders([
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ])->timeout(60)->post('https://messaging.kilakona.co.tz/api/v1/vendor/message/send', $payload);
        } catch (Throwable $e) {
            return $this->withCors(
                response()->json([
                    'ok' => false,
                    'error' => 'Upstream request failed',
                    'detail' => $e->getMessage(),
                ], 502),
                'SMS_CORS_ORIGIN'
            );
        }

        $status = $response->status();
        $decoded = $response->json();
        if (!is_array($decoded)) {
            return $this->withCors(
                response()->json([
                    'ok' => false,
                    'error' => 'Non-JSON response from SMS provider',
                    'raw' => $response->body(),
                ], $status >= 400 ? $status : 502),
                'SMS_CORS_ORIGIN'
            );
        }

        return $this->withCors(
            response()->json([
                'ok' => $status >= 200 && $status < 300,
                'provider' => $decoded,
            ], ($status >= 100 && $status < 600) ? $status : 200, [], JSON_UNESCAPED_SLASHES),
            'SMS_CORS_ORIGIN'
        );
    }
}
