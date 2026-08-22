<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_messages', 'media_url')) {
                $table->text('media_url')->nullable()->after('content');
            }
            if (!Schema::hasColumn('chat_messages', 'media_type')) {
                $table->string('media_type', 50)->nullable()->after('media_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            if (Schema::hasColumn('chat_messages', 'media_url')) {
                $table->dropColumn('media_url');
            }
            if (Schema::hasColumn('chat_messages', 'media_type')) {
                $table->dropColumn('media_type');
            }
        });
    }
};
