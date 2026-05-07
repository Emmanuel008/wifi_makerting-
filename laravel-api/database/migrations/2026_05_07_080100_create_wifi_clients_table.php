<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wifi_clients')) {
            return;
        }

        Schema::create('wifi_clients', function (Blueprint $table): void {
            $table->unsignedBigInteger('id', true);
            $table->string('phone', 32)->unique('uniq_wifi_clients_phone');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index('created_at', 'idx_wifi_clients_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wifi_clients');
    }
};
