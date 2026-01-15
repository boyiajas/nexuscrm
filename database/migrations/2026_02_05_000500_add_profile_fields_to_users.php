<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'middle_initial')) {
                $table->string('middle_initial', 1)->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('middle_initial');
            }
            if (!Schema::hasColumn('users', 'primary_phone')) {
                $table->string('primary_phone')->nullable()->after('department');
            }
            if (!Schema::hasColumn('users', 'secondary_phone')) {
                $table->string('secondary_phone')->nullable()->after('primary_phone');
            }
            if (!Schema::hasColumn('users', 'inactivity_timeout')) {
                $table->unsignedInteger('inactivity_timeout')->nullable()->after('secondary_phone');
            }
            if (!Schema::hasColumn('users', 'is_provider')) {
                $table->boolean('is_provider')->default(false)->after('inactivity_timeout');
            }
            if (!Schema::hasColumn('users', 'is_time_clock_user')) {
                $table->boolean('is_time_clock_user')->default(false)->after('is_provider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drops = [
                'username',
                'first_name',
                'middle_initial',
                'last_name',
                'primary_phone',
                'secondary_phone',
                'inactivity_timeout',
                'is_provider',
                'is_time_clock_user',
            ];
            foreach ($drops as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
