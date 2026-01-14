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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            $table->string('name')->unique();
            $table->string('description')->nullable();

            // Optional: if you want to track ownership
            // $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
        });

        // If users have department field stored as string (e.g. "Collections", "Sales")
        // you don’t need a pivot. If you want FK instead, let me know and I can generate that too.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
