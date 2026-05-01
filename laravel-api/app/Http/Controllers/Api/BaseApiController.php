<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    protected function withCors(JsonResponse $response, ?string $specificEnvKey = null, ?string $fallbackEnvKey = null): JsonResponse
    {
        $origin = '*';
        if ($specificEnvKey !== null) {
            $origin = (string) env($specificEnvKey, '');
        }

        if ($origin === '' && $fallbackEnvKey !== null) {
            $origin = (string) env($fallbackEnvKey, '');
        }

        if ($origin === '') {
            $origin = '*';
        }

        return $response
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    protected function decodeJsonBody(string $raw): ?array
    {
        $body = json_decode($raw, true);
        return is_array($body) ? $body : null;
    }
}
