<?php

namespace Database\Seeders;

use App\Models\Organization\User;
use Illuminate\Database\Seeder;

/**
 * 管理员 Seeder：张三 / admin@admin.com / 123456，拥有全部权限
 *
 * 用户名使用个人姓名，岗位由角色（system_admin）体现。
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['username' => '张三'],
            [
                'name' => '张三',
                'email' => 'admin@admin.com',
                'password' => '123456',
                'status' => 1,
            ]
        );

        $admin->syncRoles(['system_admin']);
    }
}
