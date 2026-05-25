<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@asanga.mn'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'phone' => null,
                'password' => Hash::make('ChangeMe@123456'),
                'role' => 'admin',
                'is_verified' => true,
            ]
        );
    }
}
