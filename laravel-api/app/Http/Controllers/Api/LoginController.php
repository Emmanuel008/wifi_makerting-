<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class LoginController extends BaseApiController
{
    public function __invoke(Request $request)
    {
        $body = $this->decodeJsonBody($request->getContent());
        if ($body === null) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Invalid JSON body'], 400),
                'LOGIN_CORS_ORIGIN'
            );
        }

        $email = isset($body['email']) ? strtolower(trim((string) $body['email'])) : '';
        $plainPassword = isset($body['password']) ? (string) $body['password'] : '';
        if ($email === '' || !str_contains($email, '@') || $plainPassword === '') {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'email and password are required'], 400),
                'LOGIN_CORS_ORIGIN'
            );
        }

        try {
            $row = DB::table('managed_users')
                ->select(['id', 'email', 'name', 'role', 'password_hash'])
                ->where('email', $email)
                ->first();
        } catch (Throwable) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Database connection failed'], 500),
                'LOGIN_CORS_ORIGIN'
            );
        }

        if ($row === null || !password_verify($plainPassword, (string) $row->password_hash)) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Invalid email or password'], 401),
                'LOGIN_CORS_ORIGIN'
            );
        }

        $role = (string) ($row->role ?? 'business');
        if ($role !== 'admin' && $role !== 'business') {
            $role = 'business';
        }

        return $this->withCors(
            response()->json([
                'ok' => true,
                'user' => [
                    'id' => (int) $row->id,
                    'email' => (string) $row->email,
                    'name' => (string) ($row->name ?? ''),
                    'role' => $role,
                ],
            ], 200, [], JSON_UNESCAPED_SLASHES),
            'LOGIN_CORS_ORIGIN'
        );
    }
}
