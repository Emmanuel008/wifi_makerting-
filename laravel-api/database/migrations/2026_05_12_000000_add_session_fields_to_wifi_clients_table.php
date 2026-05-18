<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wifi_clients')) {
            return;
        }

        Schema::table('wifi_clients', function (Blueprint $table): void {
            if (!Schema::hasColumn('wifi_clients', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('ip_address');
            }
            if (!Schema::hasColumn('wifi_clients', 'session_minutes')) {
                // Default session length: 8 hours = 480 minutes
                $table->unsignedSmallInteger('session_minutes')->default(480)->after('is_active');
            }
            if (!Schema::hasColumn('wifi_clients', 'session_started_at')) {
                $table->timestamp('session_started_at')->nullable()->after('session_minutes');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('wifi_clients')) {
            return;
        }

        Schema::table('wifi_clients', function (Blueprint $table): void {
            foreach (['session_started_at', 'session_minutes', 'is_active'] as $col) {
                if (Schema::hasColumn('wifi_clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
