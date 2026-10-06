<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'recorder@demo.local'],
            [
                'name' => '张录入(普通录入)',
                'role' => User::ROLE_RECORDER,
                'api_token' => 'demo-recorder-token',
                'password' => 'password',
            ]
        );

        User::updateOrCreate(
            ['email' => 'supervisor@demo.local'],
            [
                'name' => '王值班(值班员)',
                'role' => User::ROLE_SUPERVISOR,
                'api_token' => 'demo-supervisor-token',
                'password' => 'password',
            ]
        );
    }
}
