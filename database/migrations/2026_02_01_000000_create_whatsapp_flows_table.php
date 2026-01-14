<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('template_sid');
            $table->string('template_name')->nullable();
            $table->string('template_language')->nullable();
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->json('flow_definition');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flows');
    }
};
