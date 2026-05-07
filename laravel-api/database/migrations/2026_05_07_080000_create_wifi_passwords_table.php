<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wifi_passwords')) {
            return;
        }

        Schema::create('wifi_passwords', function (Blueprint $table): void {
            $table->unsignedBigInteger('id', true);
            $table->string('password_hash', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wifi_passwords');
    }
};
