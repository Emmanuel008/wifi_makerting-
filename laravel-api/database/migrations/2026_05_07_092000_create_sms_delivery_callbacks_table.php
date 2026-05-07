<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_delivery_callbacks')) {
            return;
        }

        Schema::create('sms_delivery_callbacks', function (Blueprint $table): void {
            $table->unsignedBigInteger('id', true);
            $table->string('provider_message_id', 128)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status', 64)->nullable();
            $table->json('payload');
            $table->string('client_ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('provider_message_id', 'idx_sms_cb_msg_id');
            $table->index('phone', 'idx_sms_cb_phone');
            $table->index('status', 'idx_sms_cb_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_delivery_callbacks');
    }
};
