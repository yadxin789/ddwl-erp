<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 账号体系与顶部用户菜单：
 *  - 顶部下拉有基本资料/修改密码/上传头像，没有二次验证；
 *  - 个人资料支持默认头像与上传头像；
 *  - 忘记密码走“管理员注销旧账号 -> 本人重新注册”，注册后待启用。
 */
class AuthProfileTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_offers_register_and_admin_delete_notice(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('注册账号')
            ->assertSee('忘记密码：请联系管理员注销旧账号后重新注册');
    }

    public function test_guest_registration_creates_disabled_account(): void
    {
        $payload = [
            'username' => 'reg_test_user',
            'name' => '注册测试',
            'email' => 'reg_test_user@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $this->post('/register', $payload)
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'username' => 'reg_test_user',
            'name' => '注册测试',
            'status' => 0,
        ]);

        $this->post('/login', ['account' => 'reg_test_user', 'password' => 'secret123'])
            ->assertSessionHasErrors('account');

        $this->assertGuest();
    }

    public function test_topbar_menu_matches_reference_and_has_no_mfa(): void
    {
        $user = User::where('username', '张三')->firstOrFail();
        $this->actingAs($user);

        $this->get('/')
            ->assertOk()
            ->assertSee('基本资料')
            ->assertSee('修改密码')
            ->assertSee('上传头像')
            ->assertSee('AI助手')
            ->assertSee('企业微信')
            ->assertSee(route('office.notifications'))
            ->assertSee('topbar-badge is-zero')
            ->assertSee('>0</span>', false)
            ->assertDontSee('账号安全')
            ->assertDontSee('二次验证');
    }

    public function test_profile_avatar_page_accepts_default_and_uploaded_avatar(): void
    {
        Storage::fake('public');
        $user = User::where('username', '钱七')->firstOrFail();
        $this->actingAs($user);

        $this->get('/profile/avatar')
            ->assertOk()
            ->assertSee('默认头像仓库')
            ->assertSee('avatar1.svg');

        $this->post('/profile/avatar', ['default_avatar' => 'defaults/avatar1.svg'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('defaults/avatar1.svg', $user->fresh()->avatar);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent('avatar.png', $png);

        $this->post('/profile/avatar', ['avatar' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $avatar = $user->fresh()->avatar;
        $this->assertNotSame('defaults/avatar1.svg', $avatar);
        Storage::disk('public')->assertExists('avatars/' . $avatar);
        $this->assertStringContainsString('storage/avatars/', $user->fresh()->avatar_url);
    }

    public function test_admin_can_close_account_and_same_login_name_can_register_again(): void
    {
        $admin = User::where('username', '张三')->firstOrFail();
        $old = User::create([
            'username' => 'old_account',
            'name' => '旧账号',
            'password' => 'secret123',
            'status' => 1,
        ]);

        $this->actingAs($admin)
            ->delete('/users/' . $old->id)
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $old->id, 'status' => 0]);

        // 真实流程：管理员注销后退出，本人在登录页重新注册。
        $this->post('/logout')->assertRedirect(route('login'));

        $this->post('/register', [
            'username' => 'old_account',
            'name' => '重新注册',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'username' => 'old_account',
            'name' => '重新注册',
            'status' => 0,
        ]);
    }
}
