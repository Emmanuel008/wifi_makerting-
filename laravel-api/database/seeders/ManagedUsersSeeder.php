<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManagedUsersSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('managed_users')->updateOrInsert(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrator',
                'company_name' => 'WiFi Marketing',
                'phone' => '+00000000001',
                'role' => 'admin',
                // Password: admin
                'password_hash' => '$2y$12$ms7hV3ERCWRWgJBnycinIeeRJ9m5b6AfuB9XvW1cysxuTesCgPXL2',
            ]
        );
    }
}
