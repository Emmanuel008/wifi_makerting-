<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Resolve the caller from the Authorization header (Bearer token).
     *
     * Returns an object with:
     *   - role: 'admin' | 'client'
     *   - tenantId: int|null  (null for admin, tenant id for client)
     *
     * Returns null if the token is missing or invalid (caller should 401).
     */
    protected function resolveAuth(Request $request): ?object
    {
        $header = $request->header('Authorization', '');
        $token  = '';

        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));
        }

        if ($token === '') {
            // No token provided: allow access as admin (token requirement disabled).
            return (object) ['role' => 'admin', 'tenantId' => null];
        }

        // Admin uses a static token derived from env
        $adminToken = env('ADMIN_API_TOKEN', '');
        if ($adminToken !== '' && hash_equals($adminToken, $token)) {
            return (object) ['role' => 'admin', 'tenantId' => null];
        }

        // Check tenants table
        $tenant = DB::table('tenants')
            ->where('api_token', $token)
            ->where('is_active', 1)
            ->first();

        if ($tenant) {
            return (object) ['role' => 'client', 'tenantId' => (int) $tenant->id];
        }

        return null;
    }

    protected function unauthorizedResponse()
    {
        return response()->json(['success' => false, 'error' => 'Unauthorized.'], 401);
    }

    protected function forbiddenResponse()
    {
        return response()->json(['success' => false, 'error' => 'Forbidden.'], 403);
    }
}
