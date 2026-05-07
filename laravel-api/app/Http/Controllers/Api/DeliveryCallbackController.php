<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliveryCallbackController extends BaseApiController
{
    public function receive(Request $request)
    {
        $payload = $request->all();
        if (!is_array($payload) || $payload === []) {
            $decoded = $this->decodeJsonBody($request->getContent());
            $payload = is_array($decoded) ? $decoded : [];
        }

        $providerMessageId = $this->firstNonEmpty([
            $payload['messageId'] ?? null,
            $payload['message_id'] ?? null,
            $payload['id'] ?? null,
            $payload['smsId'] ?? null,
        ]);

        $phone = $this->firstNonEmpty([
            $payload['phone'] ?? null,
            $payload['msisdn'] ?? null,
            $payload['recipient'] ?? null,
            $payload['to'] ?? null,
        ]);

        $status = $this->firstNonEmpty([
            $payload['status'] ?? null,
            $payload['deliveryStatus'] ?? null,
            $payload['state'] ?? null,
        ]);

        try {
            DB::table('sms_delivery_callbacks')->insert([
                'provider_message_id' => $providerMessageId,
                'phone' => $phone,
                'status' => $status,
                'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'client_ip' => trim((string) $request->ip()) ?: null,
                'user_agent' => (string) $request->userAgent(),
            ]);
        } catch (Throwable) {
            return response()->json([
                'sucess' => false,
                'error' => 'Could not save delivery callback. Run migrations first.',
            ], 500);
        }

        return response()->json([
            'sucess' => true,
            'message' => 'Delivery callback received',
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }

    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }
}
