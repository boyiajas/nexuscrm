<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('delivery_error_code', 64)->nullable()->after('delivery_status_at');
            $table->text('delivery_error_message')->nullable()->after('delivery_error_code');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['delivery_error_code', 'delivery_error_message']);
        });
    }
};
