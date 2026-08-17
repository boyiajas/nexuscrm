<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates_cache', function (Blueprint $table) {
            $table->id();
            $table->string('meta_id')->nullable()->index();
            $table->string('sid')->unique();           // template name (used as ID)
            $table->string('friendly_name');
            $table->string('language', 20)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('status', 30)->nullable();
            $table->text('body_preview')->nullable();
            $table->string('header_format', 20)->nullable();
            $table->text('header_text')->nullable();
            $table->text('footer_text')->nullable();
            $table->json('variables')->nullable();     // {"body_1":"...", "body_2":"..."}
            $table->json('media_urls')->nullable();    // ["https://..."]
            $table->json('buttons')->nullable();
            $table->json('raw_whatsapp')->nullable();  // full whatsapp sub-object from Meta
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates_cache');
    }
};
