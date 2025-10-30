<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Portfolio Admin', 'email' => 'admin@example.com', 'password' => 'secret123'],
            ['name' => 'Market Analyst', 'email' => 'analyst@example.com', 'password' => 'secret123'],
        ];

        foreach ($users as $data) {
            if (User::query()->where('email', $data['email'])->exists()) {
                continue;
            }

            User::factory()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);
        }
    }
}
