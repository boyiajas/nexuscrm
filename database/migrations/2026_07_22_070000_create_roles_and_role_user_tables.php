<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedInteger('whatsapp_daily_limit')->nullable()->default(500);
                $table->boolean('is_system')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['role_id', 'user_id']);
            });
        }

        $now = now();
        $defaultDescriptions = [
            User::ROLE_SUPER_ADMIN => 'Full system ownership and unrestricted administration.',
            User::ROLE_ADMIN => 'Administrative access to operational and configuration areas.',
            User::ROLE_MANAGER => 'Operational management and reporting access.',
            User::ROLE_CALL_CENTRE_MANAGER => 'Call centre management and oversight access.',
            User::ROLE_TEAM_LEADER => 'Team supervision and operational oversight access.',
            User::ROLE_AGENT => 'Frontline operational user role.',
            User::ROLE_STAFF_LEGACY => 'Legacy staff operational role.',
            User::ROLE_AUDITOR => 'Read-only audit and governance access.',
            User::ROLE_COMPLIANCE_OFFICER => 'Compliance oversight and approval access.',
            User::ROLE_READ_ONLY_REVIEWER => 'Read-only reviewer access for sensitive operational data.',
        ];

        foreach (User::ALL_ROLES as $code) {
            $existingRole = DB::table('roles')->where('code', $code)->first();

            if ($existingRole) {
                DB::table('roles')
                    ->where('id', $existingRole->id)
                    ->update([
                        'name' => str_replace('_', ' ', $code),
                        'description' => $defaultDescriptions[$code] ?? null,
                        'whatsapp_daily_limit' => 500,
                        'is_system' => true,
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('roles')->insert([
                    'code' => $code,
                    'name' => str_replace('_', ' ', $code),
                    'description' => $defaultDescriptions[$code] ?? null,
                    'whatsapp_daily_limit' => 500,
                    'is_system' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $roleIdsByCode = DB::table('roles')->pluck('id', 'code');
        DB::table('users')->select(['id', 'role'])->orderBy('id')->get()->each(function ($user) use ($roleIdsByCode, $now) {
            $roleId = $roleIdsByCode[$user->role] ?? null;
            if (!$roleId) {
                return;
            }

            $existingAssignment = DB::table('role_user')
                ->where('role_id', $roleId)
                ->where('user_id', $user->id)
                ->first();

            if ($existingAssignment) {
                DB::table('role_user')
                    ->where('id', $existingAssignment->id)
                    ->update([
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('role_user')->insert([
                    'role_id' => $roleId,
                    'user_id' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
