<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    protected function withCors(JsonResponse $response, ?string $specificEnvKey = null, ?string $fallbackEnvKey = null): JsonResponse
    {
        return $response;
    }

    protected function decodeJsonBody(string $raw): ?array
    {
        $body = json_decode($raw, true);
        return is_array($body) ? $body : null;
    }
}
