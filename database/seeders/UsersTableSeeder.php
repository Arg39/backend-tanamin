<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'id' => (string) Str::uuid(),
            'role' => 'admin',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'username' => 'admin',
            'email' => 'admin@course.com',
            'telephone' => '08123456789',
            'password' => Hash::make('abc123'),
            'token' => null,
            'status' => 'active',
            'photo_profile' => null,
        ]);
    }
}