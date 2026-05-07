<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sms_outbox')) {
            return;
        }

        Schema::create('sms_outbox', function (Blueprint $table): void {
            $table->unsignedBigInteger('id', true);
            $table->string('sender_id', 64);
            $table->text('message');
            $table->longText('contacts_csv');
            $table->unsignedInteger('contacts_count')->default(0);
            $table->text('delivery_report_url')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->longText('provider_response')->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index('status', 'idx_sms_outbox_status');
            $table->index('created_at', 'idx_sms_outbox_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_outbox');
    }
};
