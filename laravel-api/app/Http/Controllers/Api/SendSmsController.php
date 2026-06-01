<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SendSmsController extends BaseApiController
{
    /**
     * POST /api/send-sms
     *
     * JSON body:
     *   senderId           – string  e.g. "NILETEE"
     *   message            – string
     *   contacts           – comma-separated phone numbers
     *   deliveryReportUrl  – optional delivery callback URL
     *
     * multipart/form-data:
     *   senderId, message, deliveryReportUrl (optional), contactsFile (csv/txt), contacts (optional extra numbers)
     */
    public function send(Request $request)
    {
        $jsonBody = $this->decodeJsonBody($request);

        $senderId = trim((string) $this->firstNonEmpty(
            ['senderId'],
            $request->all(),
            is_array($jsonBody) ? $jsonBody : []
        ));

        $message = trim((string) $this->firstNonEmpty(
            ['message'],
            $request->all(),
            is_array($jsonBody) ? $jsonBody : []
        ));

        $deliveryReportUrl = trim((string) $this->firstNonEmpty(
            ['deliveryReportUrl'],
            $request->all(),
            is_array($jsonBody) ? $jsonBody : []
        ));

        $numbers = [];

        if ($request->hasFile('contactsFile')) {
            $numbers = $this->parseContactsFromUploadedFile($request->file('contactsFile'));
        }

        $contacts = trim((string) $this->firstNonEmpty(
            ['contacts'],
            $request->all(),
            is_array($jsonBody) ? $jsonBody : []
        ));

        if ($contacts !== '') {
            $manual = $this->normalizeContactList($contacts);
            $numbers = array_values(array_unique(array_merge($numbers, $manual)));
        }

        if ($senderId === '' || $message === '') {
            return response()->json([
                'success' => false,
                'error'   => 'senderId and message are required.',
            ], 422);
        }

        if (count($numbers) === 0) {
            return response()->json([
                'success' => false,
                'error'   => 'Provide contacts text or upload a contactsFile with phone numbers.',
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
        $result = $this->callProvider($senderId, $message, $numbers, $deliveryReportUrl);

        // Update outbox with provider response
        DB::table('sms_outbox')->where('id', $outboxId)->update([
            'status'            => $result['success'] ? 'sent' : 'failed',
            'http_status'       => $result['http_status'] ?? null,
            'provider_response' => isset($result['body']) ? substr($result['body'], 0, 16000) : null,
            'error_detail'      => $result['error'] ?? null,
        ]);

        // Insert per-recipient rows
        if (count($numbers) > 0) {
            $recipientRows = array_map(fn ($n) => [
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
                'success'   => false,
                'error'     => $result['error'] ?? 'SMS provider returned an error.',
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

    private function normalizeContactList(string $contacts): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                fn ($value) => preg_replace('/\s+/', '', trim((string) $value)),
                preg_split('/[\n,;]+/', $contacts) ?: []
            ),
            fn ($value) => $value !== '' && preg_match('/\d/', $value)
        )));
    }

    private function parseContactsFromUploadedFile(UploadedFile $file): array
    {
        $content = @file_get_contents($file->getRealPath() ?: '');

        if ($content === false || $content === '') {
            return [];
        }

        return $this->parseContactsFromText($content);
    }

    private function parseContactsFromText(string $text): array
    {
        $numbers = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = str_getcsv($line);
            $candidate = preg_replace('/\s+/', '', trim((string) ($parts[0] ?? '')));

            if ($candidate === '' || !preg_match('/\d/', $candidate)) {
                continue;
            }

            $numbers[] = $candidate;
        }

        return array_values(array_unique($numbers));
    }

    /**
     * Call the configured SMS provider.
     * Supports: kilakona  (set SMS_PROVIDER=kilakona in .env)
     * Add more providers as needed.
     */
    private function callProvider(
        string $senderId,
        string $message,
        array $numbers,
        string $deliveryReportUrl = ''
    ): array {
        $provider = strtolower(env('SMS_PROVIDER', 'kilakona'));

        return match ($provider) {
            'kilakona' => $this->sendViaKilakona($senderId, $message, $numbers, $deliveryReportUrl),
            default    => [
                'success'     => false,
                'error'       => "Unknown SMS provider: {$provider}",
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
    private function sendViaKilakona(
        string $senderId,
        string $message,
        array $numbers,
        string $deliveryReportUrl = ''
    ): array {
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

        $resolvedDeliveryUrl = $deliveryReportUrl !== ''
            ? $deliveryReportUrl
            : (string) env('KILAKONA_DELIVERY_URL', '');

        if ($resolvedDeliveryUrl !== '') {
            $payload['deliveryReportUrl'] = $resolvedDeliveryUrl;
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
