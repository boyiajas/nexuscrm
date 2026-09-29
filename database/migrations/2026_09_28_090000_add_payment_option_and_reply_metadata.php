<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('payment_option', 30)->nullable()->after('total_payment_amount')->index();
            $table->timestamp('payment_option_updated_at')->nullable()->after('payment_option');
        });

        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->string('reply_type', 50)->nullable()->after('last_response_at')->index();
            $table->string('reply_label')->nullable()->after('reply_type');
            $table->string('reply_key')->nullable()->after('reply_label');
            $table->string('reply_source', 100)->nullable()->after('reply_key');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_whatsapp_recipients', function (Blueprint $table) {
            $table->dropIndex(['reply_type']);
            $table->dropColumn(['reply_type', 'reply_label', 'reply_key', 'reply_source']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['payment_option']);
            $table->dropColumn(['payment_option', 'payment_option_updated_at']);
        });
    }
};
