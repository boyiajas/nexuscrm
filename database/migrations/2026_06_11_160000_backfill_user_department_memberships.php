<?php

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::query()->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $departmentId = $user->department_id;

                if (!$departmentId && !empty($user->department)) {
                    $departmentId = Department::query()
                        ->where('name', $user->department)
                        ->value('id');
                }

                if ($departmentId) {
                    $user->departments()->syncWithoutDetaching([(int) $departmentId]);
                }
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty: this is a data backfill and should not
        // remove department memberships that may have been legitimately added later.
    }
};
