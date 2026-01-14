<?php

// database/migrations/2025_01_01_000003_create_campaigns_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('channels'); // ["WhatsApp","Email","SMS"]
            $table->enum('status', ['Draft','Scheduled','Active','Paused','Completed'])->default('Draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->text('template_body')->nullable();
            $table->timestamps(); // created_at ≈ createdAt
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
