<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add tenant_id to wifi_clients
        if (Schema::hasTable('wifi_clients') && !Schema::hasColumn('wifi_clients', 'tenant_id')) {
            Schema::table('wifi_clients', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index('idx_wifi_clients_tenant');
            });
        }

        // Add tenant_id to wifi_passwords
        if (Schema::hasTable('wifi_passwords') && !Schema::hasColumn('wifi_passwords', 'tenant_id')) {
            Schema::table('wifi_passwords', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index('idx_wifi_passwords_tenant');
            });
        }

        // Add tenant_id to sms_outbox
        if (Schema::hasTable('sms_outbox') && !Schema::hasColumn('sms_outbox', 'tenant_id')) {
            Schema::table('sms_outbox', function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index('idx_sms_outbox_tenant');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wifi_clients') && Schema::hasColumn('wifi_clients', 'tenant_id')) {
            Schema::table('wifi_clients', function (Blueprint $table): void {
                $table->dropIndex('idx_wifi_clients_tenant');
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('wifi_passwords') && Schema::hasColumn('wifi_passwords', 'tenant_id')) {
            Schema::table('wifi_passwords', function (Blueprint $table): void {
                $table->dropIndex('idx_wifi_passwords_tenant');
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('sms_outbox') && Schema::hasColumn('sms_outbox', 'tenant_id')) {
            Schema::table('sms_outbox', function (Blueprint $table): void {
                $table->dropIndex('idx_sms_outbox_tenant');
                $table->dropColumn('tenant_id');
            });
        }
    }
};
