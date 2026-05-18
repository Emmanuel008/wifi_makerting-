<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseApiController extends Controller
{
    protected function decodeJsonBody(Request $request): array
    {
        $body = $request->getContent();
        if (!$body) return [];
        return json_decode($body, true) ?? [];
    }

    protected function firstNonEmpty(array $keys, array ...$sources): mixed
    {
        foreach ($keys as $key) {
            foreach ($sources as $source) {
                $val = $source[$key] ?? null;
                if ($val !== null && $val !== '') return $val;
            }
        }
        return null;
    }
}
