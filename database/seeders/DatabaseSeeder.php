<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'clerk@site.local'],
            [
                'name' => '李录入（普通检查人员）',
                'password' => 'password',
                'role' => User::ROLE_CLERK,
            ],
        );

        User::updateOrCreate(
            ['email' => 'supervisor@site.local'],
            [
                'name' => '王值班（值班员）',
                'password' => 'password',
                'role' => User::ROLE_SUPERVISOR,
            ],
        );
    }
}
