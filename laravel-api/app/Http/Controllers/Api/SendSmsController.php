<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SendSmsController extends BaseApiController
{
    public function send(Request $request)
    {
        $senderId = trim((string) ($request->senderId ?? ''));
        $message = trim((string) ($request->message ?? ''));
        $deliveryReportUrl = trim((string) env('SMS_DELIVERY_REPORT_URL', ''));
        if ($deliveryReportUrl === '') {
            $appUrl = rtrim(trim((string) env('APP_URL', '')), '/');
            if ($appUrl !== '') {
                $deliveryReportUrl = $appUrl . '/api/delivery-callback';
            }
        }
        $contacts = trim((string) ($request->contacts ?? ''));

        if ($senderId === '' || $message === '') {
            return response()->json(['sucess' => false, 'error' => 'senderId and message are required'], 400);
        }

        $normalizedFromText = $this->normalizeContactsFromText($contacts);
        $normalizedFromFile = $this->normalizeContactsFromFile($request->file('contactsFile'));
        $finalContacts = array_values(array_unique(array_merge($normalizedFromText, $normalizedFromFile)));

        if ($finalContacts === []) {
            return response()->json([
                'sucess' => false,
                'error' => 'Provide contacts as comma-separated string or upload contactsFile with numbers in column A.',
            ], 400);
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
            return response()->json([
                'sucess' => false,
                'error' => 'Set SMS_API_KEY and SMS_API_SECRET in laravel-api/.env',
            ], 500);
        }

        $payload = [
            'senderId' => $senderId,
            'messageType' => 'text',
            'message' => $message,
            'contacts' => implode(',', $finalContacts),
        ];
        if ($deliveryReportUrl !== '') {
            $payload['deliveryReportUrl'] = $deliveryReportUrl;
        }

        $outboxIds = $this->createOutboxEntries(
            $senderId,
            $message,
            $finalContacts,
            $deliveryReportUrl !== '' ? $deliveryReportUrl : null
        );
        if ($outboxIds === []) {
            return response()->json([
                'sucess' => false,
                'error' => 'Could not save SMS history to outbox. Run migrations first.',
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ])->timeout(60)->post('https://messaging.kilakona.co.tz/api/v1/vendor/message/send', $payload);
        } catch (Throwable $e) {
            $this->updateOutboxEntries($outboxIds, [
                'status' => 'failed',
                'error_detail' => $e->getMessage(),
            ]);
            return response()->json([
                'sucess' => false,
                'error' => 'Upstream request failed',
                'detail' => $e->getMessage(),
            ], 502);
        }

        $status = $response->status();
        $decoded = $response->json();
        if (!is_array($decoded)) {
            $this->updateOutboxEntries($outboxIds, [
                'status' => 'failed',
                'http_status' => $status,
                'provider_response' => $response->body(),
                'error_detail' => 'Non-JSON response from SMS provider',
            ]);
            return response()->json([
                'sucess' => false,
                'error' => 'Non-JSON response from SMS provider',
                'raw' => $response->body(),
            ], $status >= 400 ? $status : 502);
        }

        $this->updateOutboxEntries($outboxIds, [
            'status' => ($status >= 200 && $status < 300) ? 'sent' : 'failed',
            'http_status' => $status,
            'provider_response' => json_encode($decoded, JSON_UNESCAPED_SLASHES),
            'error_detail' => null,
        ]);

        return response()->json([
            'sucess' => $status >= 200 && $status < 300,
            'contactsCount' => count($finalContacts),
            'outboxIds' => $outboxIds,
            'outboxRows' => count($outboxIds),
            'provider' => $decoded,
        ], ($status >= 100 && $status < 600) ? $status : 200, [], JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param list<string> $phones
     * @return list<int>
     */
    private function createOutboxEntries(
        string $senderId,
        string $message,
        array $phones,
        ?string $deliveryReportUrl
    ): array {
        $characterCount = mb_strlen($message);
        $smsParts = max(1, (int) ceil($characterCount / 160));
        $ids = [];

        foreach ($phones as $phone) {
            try {
                $row = [
                    'sender_id' => $senderId,
                    'message' => $message,
                    'contacts_csv' => $phone,
                    'contacts_count' => 1,
                    'delivery_report_url' => $deliveryReportUrl,
                    'status' => 'pending',
                    'http_status' => null,
                    'provider_response' => null,
                    'error_detail' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('sms_outbox', 'character_count')) {
                    $row['character_count'] = $characterCount;
                }
                if (Schema::hasColumn('sms_outbox', 'sms_parts')) {
                    $row['sms_parts'] = $smsParts;
                }

                $id = (int) DB::table('sms_outbox')->insertGetId($row);
                $ids[] = $id;
            } catch (Throwable) {
                // Keep going for other recipients.
            }
        }

        return $ids;
    }

    /**
     * @param list<int> $ids
     */
    private function updateOutboxEntries(array $ids, array $data): void
    {
        if ($ids === []) {
            return;
        }

        try {
            $data['updated_at'] = now();
            DB::table('sms_outbox')->whereIn('id', $ids)->update($data);
        } catch (Throwable) {
            // Do not fail SMS API when history logging fails.
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeContactsFromText(string $contacts): array
    {
        if ($contacts === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $contacts) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            $phone = $this->normalizePhone($part);
            if ($phone !== null) {
                $normalized[] = $phone;
            }
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function normalizeContactsFromFile(?UploadedFile $file): array
    {
        if ($file === null || !$file->isValid()) {
            return [];
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === 'csv' || $ext === 'txt') {
            return $this->normalizeContactsFromText((string) $file->get());
        }

        if ($ext === 'xlsx') {
            $rows = $this->extractFirstColumnFromXlsx($file->getRealPath() ?: '');
            $normalized = [];
            foreach ($rows as $rowValue) {
                $phone = $this->normalizePhone($rowValue);
                if ($phone !== null) {
                    $normalized[] = $phone;
                }
            }
            return $normalized;
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function extractFirstColumnFromXlsx(string $path): array
    {
        if ($path === '') {
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!is_string($sheetXml) || $sheetXml === '') {
            return [];
        }

        $sharedStrings = [];
        if (is_string($sharedStringsXml) && $sharedStringsXml !== '') {
            $shared = @simplexml_load_string($sharedStringsXml);
            if ($shared !== false) {
                foreach ($shared->si as $si) {
                    $texts = [];
                    if (isset($si->t)) {
                        $texts[] = (string) $si->t;
                    } else {
                        foreach ($si->r as $run) {
                            $texts[] = (string) ($run->t ?? '');
                        }
                    }
                    $sharedStrings[] = trim(implode('', $texts));
                }
            }
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false) {
            return [];
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                $cellRef = (string) ($cell['r'] ?? '');
                if (!str_starts_with($cellRef, 'A')) {
                    continue;
                }

                $type = (string) ($cell['t'] ?? '');
                $value = trim((string) ($cell->v ?? ''));
                if ($value === '') {
                    continue;
                }

                if ($type === 's') {
                    $index = (int) $value;
                    if (isset($sharedStrings[$index])) {
                        $rows[] = $sharedStrings[$index];
                    }
                } else {
                    $rows[] = $value;
                }
                break;
            }
        }

        return $rows;
    }

    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($raw));
        if ($digits === null || $digits === '') {
            return null;
        }

        if (strlen($digits) === 9 && preg_match('/^[67]/', $digits) === 1) {
            return '255' . $digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '255' . substr($digits, 1);
        }

        if (str_starts_with($digits, '255') && strlen($digits) === 12) {
            return $digits;
        }

        return null;
    }
}
