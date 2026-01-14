<?php

// database/migrations/2025_01_01_000006_create_system_settings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('twilio_api_key')->nullable();
            $table->string('twilio_sid')->nullable();
            $table->string('zoomconnect_api_key')->nullable();
            $table->string('zoomconnect_base_url')->nullable();
            $table->enum('backup_frequency', ['Daily','Weekly','Monthly'])->default('Weekly');
            $table->boolean('enable_auto_backup')->default(true);
            $table->enum('email_provider', ['SMTP','Mailgun','SES'])->default('SMTP');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
