<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class LoginController extends BaseApiController
{
    public function login(Request $request)
    {
        $body = $this->decodeJsonBody($request->getContent());
        if ($body === null) {
            return response()->json(['sucess' => false, 'error' => 'Invalid JSON body'], 400);
        }

        $email = isset($body['email']) ? strtolower(trim((string) $body['email'])) : '';
        $plainPassword = isset($body['password']) ? (string) $body['password'] : '';
        if ($email === '' || !str_contains($email, '@') || $plainPassword === '') {
            return response()->json(['sucess' => false, 'error' => 'email and password are required'], 400);
        }

        try {
            $row = DB::table('managed_users')
                ->select(['id', 'email', 'name', 'role', 'password_hash'])
                ->where('email', $email)
                ->first();
        } catch (Throwable) {
            return response()->json(['sucess' => false, 'error' => 'Database connection failed'], 500);
        }

        if ($row === null || !password_verify($plainPassword, (string) $row->password_hash)) {
            return response()->json(['sucess' => false, 'error' => 'Invalid email or password'], 401);
        }

        $role = (string) ($row->role ?? 'business');
        if ($role !== 'admin' && $role !== 'business') {
            $role = 'business';
        }

        return response()->json([
                'sucess' => true,
            'user' => [
                'id' => (int) $row->id,
                'email' => (string) $row->email,
                'name' => (string) ($row->name ?? ''),
                'role' => $role,
            ],
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
