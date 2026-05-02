<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wifi_sessions')) {
            return;
        }

        Schema::create('wifi_sessions', function (Blueprint $table): void {
            $table->unsignedBigInteger('id', true);
            $table->string('phone', 32)->nullable();
            $table->string('mac', 64)->nullable();
            $table->string('device', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ssid', 128)->nullable();
            $table->string('client_ip', 64)->nullable();
            $table->dateTime('connected_at');
            $table->dateTime('last_seen_at');
            $table->dateTime('disconnected_at')->nullable();
            $table->index(['mac', 'disconnected_at'], 'idx_mac_open');
            $table->index(['phone', 'disconnected_at'], 'idx_phone_open');
            $table->index('last_seen_at', 'idx_last_seen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wifi_sessions');
    }
};
