<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;
use Throwable;

class ManagedUsersController extends BaseApiController
{
    public function __invoke(Request $request)
    {
        $body = $this->decodeJsonBody($request->getContent());
        if ($body === null) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Invalid JSON body'], 400),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            );
        }

        $action = isset($body['action']) ? strtolower(trim((string) $body['action'])) : '';
        if (!in_array($action, ['list', 'save', 'update', 'delete'], true)) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'action is required: list, save, update, or delete'], 400),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            );
        }

        if ($action === 'list') {
            return $this->handleList($body);
        }

        if ($action === 'delete') {
            return $this->handleDelete($body);
        }

        return $action === 'save' ? $this->handleSave($body) : $this->handleUpdate($body);
    }

    private function handleList(array $body)
    {
        $perPage = isset($body['perPage']) ? (int) $body['perPage'] : 10;
        $perPage = max(1, min(100, $perPage));
        $page = isset($body['page']) ? (int) $body['page'] : 1;
        $page = max(1, $page);

        try {
            $total = (int) DB::table('managed_users')->count();
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;

            $rows = DB::table('managed_users')
                ->selectRaw('id, name, company_name, email, phone, role, UNIX_TIMESTAMP(created_at) AS created_at, UNIX_TIMESTAMP(updated_at) AS updated_at')
                ->orderByDesc('id')
                ->offset($offset)
                ->limit($perPage)
                ->get();
        } catch (Throwable) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Database connection failed'], 500),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            );
        }

        $users = $rows->map(static fn ($row) => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'companyName' => (string) $row->company_name,
            'email' => (string) $row->email,
            'phone' => (string) $row->phone,
            'role' => (string) $row->role,
            'createdAt' => (int) $row->created_at * 1000,
            'updatedAt' => (int) $row->updated_at * 1000,
        ])->all();

        return $this->withCors(
            response()->json([
                'ok' => true,
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
            ], 200, [], JSON_UNESCAPED_SLASHES),
            'MANAGED_USERS_CORS_ORIGIN',
            'LOGIN_CORS_ORIGIN'
        );
    }

    private function handleDelete(array $body)
    {
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        if ($id < 1) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'id is required for delete'], 400),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            );
        }

        try {
            $deleted = DB::table('managed_users')->where('id', $id)->delete();
        } catch (Throwable) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Database connection failed'], 500),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            );
        }

        if ($deleted < 1) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'User not found'], 404),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            );
        }

        return $this->withCors(
            response()->json(['ok' => true], 200, [], JSON_UNESCAPED_SLASHES),
            'MANAGED_USERS_CORS_ORIGIN',
            'LOGIN_CORS_ORIGIN'
        );
    }

    private function handleSave(array $body)
    {
        [$name, $companyName, $email, $phone, $role, $error] = $this->validatedUserPayload($body);
        if ($error !== null) {
            return $error;
        }

        try {
            $newId = DB::table('managed_users')->insertGetId([
                'name' => $name,
                'company_name' => $companyName,
                'email' => $email,
                'phone' => $phone,
                'role' => $role,
                'password_hash' => Hash::make('admin'),
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return $this->withCors(
                    response()->json(['ok' => false, 'error' => 'Email or phone is already in use'], 409),
                    'MANAGED_USERS_CORS_ORIGIN',
                    'LOGIN_CORS_ORIGIN'
                );
            }

            throw $e;
        } catch (Throwable) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Database connection failed'], 500),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            );
        }

        return $this->withCors(
            response()->json([
                'ok' => true,
                'user' => [
                    'id' => (int) $newId,
                    'name' => $name,
                    'companyName' => $companyName,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                ],
            ], 200, [], JSON_UNESCAPED_SLASHES),
            'MANAGED_USERS_CORS_ORIGIN',
            'LOGIN_CORS_ORIGIN'
        );
    }

    private function handleUpdate(array $body)
    {
        $id = isset($body['id']) ? (int) $body['id'] : 0;
        if ($id < 1) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'id is required for update'], 400),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            );
        }

        [$name, $companyName, $email, $phone, $role, $error] = $this->validatedUserPayload($body);
        if ($error !== null) {
            return $error;
        }

        try {
            $exists = DB::table('managed_users')->where('id', $id)->exists();
            if (!$exists) {
                return $this->withCors(
                    response()->json(['ok' => false, 'error' => 'User not found'], 404),
                    'MANAGED_USERS_CORS_ORIGIN',
                    'LOGIN_CORS_ORIGIN'
                );
            }

            DB::table('managed_users')
                ->where('id', $id)
                ->update([
                    'name' => $name,
                    'company_name' => $companyName,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return $this->withCors(
                    response()->json(['ok' => false, 'error' => 'Email or phone is already in use'], 409),
                    'MANAGED_USERS_CORS_ORIGIN',
                    'LOGIN_CORS_ORIGIN'
                );
            }

            throw $e;
        } catch (Throwable) {
            return $this->withCors(
                response()->json(['ok' => false, 'error' => 'Database connection failed'], 500),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            );
        }

        return $this->withCors(
            response()->json([
                'ok' => true,
                'user' => [
                    'id' => $id,
                    'name' => $name,
                    'companyName' => $companyName,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                ],
            ], 200, [], JSON_UNESCAPED_SLASHES),
            'MANAGED_USERS_CORS_ORIGIN',
            'LOGIN_CORS_ORIGIN'
        );
    }

    private function validatedUserPayload(array $body): array
    {
        $name = isset($body['name']) ? trim((string) $body['name']) : '';
        $companyName = isset($body['companyName']) ? trim((string) $body['companyName']) : '';
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $phone = $this->normalizePhone((string) ($body['phone'] ?? ''));
        $role = $this->validRole($body['role'] ?? null);

        if ($name === '') {
            return [$name, $companyName, $email, $phone, $role, $this->withCors(
                response()->json(['ok' => false, 'error' => 'name is required'], 400),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            )];
        }
        if ($companyName === '') {
            return [$name, $companyName, $email, $phone, $role, $this->withCors(
                response()->json(['ok' => false, 'error' => 'companyName is required'], 400),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            )];
        }
        if ($email === '' || !str_contains($email, '@')) {
            return [$name, $companyName, $email, $phone, $role, $this->withCors(
                response()->json(['ok' => false, 'error' => 'valid email is required'], 400),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            )];
        }
        if ($phone === '' || strlen($phone) < 8) {
            return [$name, $companyName, $email, $phone, $role, $this->withCors(
                response()->json(['ok' => false, 'error' => 'valid phone number is required'], 400),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            )];
        }
        if ($role === null) {
            return [$name, $companyName, $email, $phone, $role, $this->withCors(
                response()->json(['ok' => false, 'error' => 'role must be admin or business'], 400),
                'MANAGED_USERS_CORS_ORIGIN',
                'LOGIN_CORS_ORIGIN'
            )];
        }

        return [$name, $companyName, $email, $phone, $role, null];
    }

    private function normalizePhone(string $phone): string
    {
        $value = trim($phone);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '+')) {
            return (string) preg_replace('/[^\d+]/', '', $value);
        }

        $digits = (string) preg_replace('/\D/', '', $value);
        return $digits !== '' ? '+' . $digits : '';
    }

    private function validRole(mixed $role): ?string
    {
        $value = is_string($role) ? strtolower(trim($role)) : '';
        return in_array($value, ['admin', 'business'], true) ? $value : null;
    }
}
