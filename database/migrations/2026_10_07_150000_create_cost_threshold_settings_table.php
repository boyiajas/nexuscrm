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
        if (!Schema::hasTable('cost_threshold_settings')) {
            Schema::create('cost_threshold_settings', function (Blueprint $table) {
                $table->id();
                $table->string('name')->default('Monthly Cost Budget Alert');
                $table->decimal('threshold_amount', 12, 2)->comment('Monthly budget threshold amount in USD');
                $table->json('bank_ids')->nullable()->comment('Selected bank IDs for this threshold');
                $table->json('notification_emails')->comment('List of notification email addresses');
                $table->json('threshold_percentages')->comment('List of threshold percentages to trigger alerts (e.g. [80, 90, 100])');
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('last_alerted_at')->nullable();
                $table->json('triggered_percentages')->nullable()->comment('Track triggered percentage tiers per month');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_threshold_settings');
    }
};
