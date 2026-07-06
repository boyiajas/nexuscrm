<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dataset', 50)->default('clients');
            $table->string('original_filename');
            $table->string('stored_path')->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('file_hash', 128)->nullable();
            $table->string('import_status', 40)->default('uploaded');
            $table->boolean('scan_enabled')->default(false);
            $table->string('scan_status', 40)->nullable();
            $table->string('scan_engine', 50)->nullable();
            $table->string('scan_signature', 255)->nullable();
            $table->text('scan_message')->nullable();
            $table->json('import_summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_uploads');
    }
};
