<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sms_outbox')) {
            return;
        }

        Schema::table('sms_outbox', function (Blueprint $table): void {
            if (!Schema::hasColumn('sms_outbox', 'character_count')) {
                $table->unsignedSmallInteger('character_count')->default(0)->after('message');
            }
            if (!Schema::hasColumn('sms_outbox', 'sms_parts')) {
                $table->unsignedSmallInteger('sms_parts')->default(1)->after('character_count');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sms_outbox')) {
            return;
        }

        Schema::table('sms_outbox', function (Blueprint $table): void {
            if (Schema::hasColumn('sms_outbox', 'sms_parts')) {
                $table->dropColumn('sms_parts');
            }
            if (Schema::hasColumn('sms_outbox', 'character_count')) {
                $table->dropColumn('character_count');
            }
        });
    }
};
