<?php

// database/migrations/2025_01_01_000001_update_users_table_for_crm.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('STAFF'); // SUPER_ADMIN | MANAGER | STAFF
            $table->string('department')->nullable(); // Sales, Support, Marketing...
            $table->boolean('mfa_enabled')->default(false);
            $table->string('mfa_type')->nullable();   // 'email' or 'totp'
            $table->string('mfa_secret')->nullable(); // for TOTP (Google Auth)
            $table->timestamp('last_login_at')->nullable();
            $table->string('status')->default('Active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'department', 'mfa_enabled', 'last_login_at', 'status'
            ]);
        });
    }
};
