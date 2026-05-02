<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('managed_users')) {
            return;
        }

        Schema::create('managed_users', function (Blueprint $table): void {
            $table->unsignedInteger('id', true);
            $table->string('name', 255);
            $table->string('company_name', 255);
            $table->string('email', 255)->unique('uq_managed_users_email');
            $table->string('phone', 64)->unique('uq_managed_users_phone');
            $table->enum('role', ['admin', 'business'])->default('business');
            $table->string('password_hash', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_users');
    }
};
