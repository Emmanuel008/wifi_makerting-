<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_outbox_recipients')) {
            return;
        }

        Schema::create('sms_outbox_recipients', function (Blueprint $table): void {
            $table->unsignedBigInteger('id', true);
            $table->unsignedBigInteger('outbox_id');
            $table->string('phone', 32);
            $table->text('message');
            $table->unsignedSmallInteger('character_count')->default(0);
            $table->unsignedSmallInteger('sms_parts')->default(1);
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->longText('provider_response')->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index('outbox_id', 'idx_sms_outbox_recipients_outbox');
            $table->index('phone', 'idx_sms_outbox_recipients_phone');
            $table->index('status', 'idx_sms_outbox_recipients_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_outbox_recipients');
    }
};
