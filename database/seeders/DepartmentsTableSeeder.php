<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentsTableSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'Collections', 'description' => 'Collections & Recovery'],
            ['name' => 'Legal',       'description' => 'Legal & Compliance'],
            ['name' => 'Sales',       'description' => 'Sales & Business Development'],
            ['name' => 'Recovery',    'description' => 'Debt Recovery Operations'],
        ];

        foreach ($defaults as $dept) {
            Department::firstOrCreate(
                ['name' => $dept['name']],
                ['description' => $dept['description']]
            );
        }
    }
}
