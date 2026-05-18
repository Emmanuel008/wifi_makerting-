<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class LoginController extends BaseApiController
{
    public function login(Request $request)
    {
        $email    = trim((string) ($request->email ?? ''));
        $password = (string) ($request->password ?? '');

        if ($email === '' || $password === '') {
            return response()->json(['success' => false, 'error' => 'Email and password are required.'], 400);
        }

        $adminEmail    = env('ADMIN_EMAIL', 'admin@admin.com');
        $adminPassword = env('ADMIN_PASSWORD', '');
        $adminName     = env('ADMIN_NAME', 'Admin');

        if (
            !hash_equals($adminEmail, $email) ||
            !hash_equals($adminPassword, $password)
        ) {
            return response()->json(['success' => false, 'error' => 'Invalid email or password.'], 401);
        }

        return response()->json([
            'success' => true,
            'user'    => [
                'id'    => 1,
                'email' => $adminEmail,
                'name'  => $adminName,
                'role'  => 'admin',
            ],
        ]);
    }
}
