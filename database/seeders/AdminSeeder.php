<?php

namespace Database\Seeders;

use App\Models\Organization\User;
use Illuminate\Database\Seeder;

/**
 * 管理员 Seeder：admin / admin@admin.com / 123456，拥有全部权限
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => '系统管理员',
                'email' => 'admin@admin.com',
                'password' => '123456',
                'status' => 1,
            ]
        );

        $admin->syncRoles(['system_admin']);
    }
}
