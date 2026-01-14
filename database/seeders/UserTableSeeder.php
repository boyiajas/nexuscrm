<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'boyiajas@gmail.com'], // unique identifier
            [
                'name'     => 'Taiwo Peter',
                'email'    => 'boyiajas@gmail.com',
                'password' => Hash::make('Gospel123'),
                'role'     => 'SUPER_ADMIN',
            ]
        );
    }
}
