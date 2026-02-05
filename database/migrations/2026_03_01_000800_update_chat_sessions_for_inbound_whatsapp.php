<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
            $table->string('platform', 50)->default('whatsapp')->change();
            $table->string('phone', 32)->nullable()->after('client_name');
        });

        // Allow sender 'client' in chat_messages
        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN sender ENUM('user','agent','system','client')");
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
            $table->string('platform', 191)->default('Twilio WhatsApp')->change();
            $table->dropColumn('phone');
        });

        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN sender ENUM('user','agent','system')");
    }
};
