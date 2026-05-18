<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wifi_clients', function (Blueprint $table) {
            $table->string('mac_address', 17)->nullable()->after('phone');
            $table->string('ip_address', 45)->nullable()->after('mac_address');
        });
    }

    public function down(): void
    {
        Schema::table('wifi_clients', function (Blueprint $table) {
            $table->dropColumn(['mac_address', 'ip_address']);
        });
    }
};
