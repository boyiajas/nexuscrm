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
        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->text('reply_label')->nullable()->change();
            $table->text('reply_key')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->string('reply_label', 255)->nullable()->change();
            $table->string('reply_key', 255)->nullable()->change();
        });
    }
};
