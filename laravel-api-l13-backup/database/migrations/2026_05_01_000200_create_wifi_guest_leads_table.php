<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wifi_guest_leads')) {
            return;
        }

        Schema::create('wifi_guest_leads', function (Blueprint $table): void {
            $table->unsignedBigInteger('id', true);
            $table->string('phone', 32);
            $table->string('name', 255)->nullable();
            $table->string('mac', 64)->nullable();
            $table->string('client_ip', 64)->nullable();
            $table->text('original_url')->nullable();
            $table->dateTime('terms_accepted_at');
            $table->dateTime('verified_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index('phone', 'idx_phone');
            $table->index('created_at', 'idx_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wifi_guest_leads');
    }
};
