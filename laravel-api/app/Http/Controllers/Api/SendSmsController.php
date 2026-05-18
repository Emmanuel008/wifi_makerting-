<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SendSmsController extends BaseApiController
{
    /**
     * POST /api/send-sms
     *
     * Body:
     *   senderId  – string  e.g. "NILETEE"
     *   message   – string
     *   contacts  – comma-separated phone numbers e.g. "2557XXXXXXXX,2557YYYYYYYY"
     */
    public function send(Request $request)
    {
        $body = $this->decodeJsonBody($request);
        if (!is_array($body) || empty($body)) {
            $body = $request->all();
        }

        $senderId = trim((string) ($body['senderId'] ?? ''));
        $message  = trim((string) ($body['message']  ?? ''));
        $contacts = trim((string) ($body['contacts']  ?? ''));

        if ($senderId === '' || $message === '' || $contacts === '') {
            return response()->json([
                'success' => false,
                'error'   => 'senderId, message, and contacts are required.',
            ], 422);
        }

        // Normalise contacts: remove whitespace, deduplicate, filter empty
        $numbers = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $contacts))
        )));

        if (count($numbers) === 0) {
            return response()->json([
                'success' => false,
                'error'   => 'No valid phone numbers provided.',
            ], 422);
        }

        $contactsCsv    = implode(',', $numbers);
        $characterCount = mb_strlen($message);
        $smsParts       = (int) ceil($characterCount / 160) ?: 1;

        // Create outbox record first (status = pending)
        $outboxId = DB::table('sms_outbox')->insertGetId([
            'sender_id'       => $senderId,
            'message'         => $message,
            'character_count' => $characterCount,
            'sms_parts'       => $smsParts,
            'contacts_csv'    => $contactsCsv,
            'contacts_count'  => count($numbers),
            'status'          => 'pending',
        ]);

        // Call SMS provider
        $result = $this->callProvider($senderId, $message, $numbers);

        // Update outbox with provider response
        DB::table('sms_outbox')->where('id', $outboxId)->update([
            'status'            => $result['success'] ? 'sent' : 'failed',
            'http_status'       => $result['http_status'] ?? null,
            'provider_response' => isset($result['body']) ? substr($result['body'], 0, 16000) : null,
            'error_detail'      => $result['error'] ?? null,
        ]);

        // Insert per-recipient rows
        if (count($numbers) > 0) {
            $recipientRows = array_map(fn($n) => [
                'outbox_id'       => $outboxId,
                'phone'           => $n,
                'message'         => $message,
                'character_count' => $characterCount,
                'sms_parts'       => $smsParts,
                'status'          => $result['success'] ? 'sent' : 'failed',
                'http_status'     => $result['http_status'] ?? null,
            ], $numbers);

            DB::table('sms_outbox_recipients')->insert($recipientRows);
        }

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error'   => $result['error'] ?? 'SMS provider returned an error.',
                'outbox_id' => $outboxId,
            ], 502);
        }

        return response()->json([
            'success'    => true,
            'outbox_id'  => $outboxId,
            'recipients' => count($numbers),
        ]);
    }

    // ------------------------------------------------------------------

    /**
     * Call the configured SMS provider.
     * Supports: kilakona  (set SMS_PROVIDER=kilakona in .env)
     * Add more providers as needed.
     */
    private function callProvider(string $senderId, string $message, array $numbers): array
    {
        $provider = strtolower(env('SMS_PROVIDER', 'kilakona'));

        return match ($provider) {
            'kilakona' => $this->sendViaKilakona($senderId, $message, $numbers),
            default    => [
                'success'    => false,
                'error'      => "Unknown SMS provider: {$provider}",
                'http_status' => null,
            ],
        };
    }

    /**
     * Kilakona SMS API — https://messaging.kilakona.co.tz/api/v1/send-message
     * Env vars:
     *   KILAKONA_API_KEY      – your API key
     *   KILAKONA_API_SECRET   – your API secret
     *   KILAKONA_DELIVERY_URL – optional delivery callback URL
     */
    private function sendViaKilakona(string $senderId, string $message, array $numbers): array
    {
        $apiKey    = (string) env('KILAKONA_API_KEY', '');
        $apiSecret = (string) env('KILAKONA_API_SECRET', '');

        if ($apiKey === '' || $apiSecret === '') {
            return ['success' => false, 'error' => 'KILAKONA_API_KEY or KILAKONA_API_SECRET is not set.', 'http_status' => null];
        }

        $payload = [
            'senderId'    => $senderId,
            'messageType' => 'text',
            'message'     => $message,
            'contacts'    => implode(',', $numbers),
        ];

        $deliveryUrl = (string) env('KILAKONA_DELIVERY_URL', '');
        if ($deliveryUrl !== '') {
            $payload['deliveryReportUrl'] = $deliveryUrl;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://messaging.kilakona.co.tz/api/v1/send-message',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'api_key: ' . $apiKey,
                'api_secret: ' . $apiSecret,
            ],
        ]);

        $body       = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['success' => false, 'error' => "cURL error: {$curlError}", 'http_status' => 0];
        }

        $decoded = json_decode($body, true);
        $success = $httpStatus >= 200 && $httpStatus < 300
            && ($decoded['success'] ?? $decoded['status'] ?? 'error') !== 'error';

        return [
            'success'     => $success,
            'http_status' => $httpStatus,
            'body'        => $body,
            'error'       => $success ? null : ($decoded['message'] ?? $decoded['error'] ?? "HTTP {$httpStatus}"),
        ];
    }
}
