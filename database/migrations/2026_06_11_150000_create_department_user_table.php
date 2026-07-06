<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['department_id', 'user_id']);
        });

        User::query()
            ->select(['id', 'department_id', 'department'])
            ->orderBy('id')
            ->chunk(200, function ($users) {
                foreach ($users as $user) {
                    $departmentId = $user->department_id;

                    if (!$departmentId && !empty($user->department)) {
                        $departmentId = Department::query()
                            ->where('name', $user->department)
                            ->value('id');
                    }

                    if ($departmentId) {
                        DB::table('department_user')->updateOrInsert([
                            'department_id' => $departmentId,
                            'user_id' => $user->id,
                        ], [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_user');
    }
};
