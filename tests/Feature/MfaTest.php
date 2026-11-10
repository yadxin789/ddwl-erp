<?php

namespace Tests\Feature;

use App\Models\Organization\User;
use App\Support\TotpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** P3-3 MFA/TOTP。 */
class MfaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_totp_matches_rfc6238_sha1_vector(): void
    {
        $totp = app(TotpService::class);
        // RFC 6238 附录 B：secret=12345678901234567890，T=59s，8 位码 94287082 → 6 位码 287082
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $key = $totp->base32Decode($secret);
        $this->assertSame('287082', $totp->codeAt($key, 1));
        $this->assertTrue($totp->verify($secret, '287082', 0, 59));
        $this->assertFalse($totp->verify($secret, '000000', 0, 59));
    }

    public function test_enable_challenge_recovery_and_disable(): void
    {
        $totp = app(TotpService::class);
        $user = User::where('username', 'wh-keeper')->firstOrFail();
        $this->actingAs($user);

        $response = $this->get(route('mfa.setup'));
        $response->assertOk()->assertSee('开启二次验证');
        $secret = session('mfa_setup_secret');
        $this->assertNotEmpty($secret);

        $code = $totp->codeAt($totp->base32Decode($secret), intdiv(time(), 30));
        $this->post(route('mfa.enable'), ['code' => $code])
            ->assertRedirect(route('mfa.setup'))
            ->assertSessionHas('mfa_recovery_plain');

        $user->refresh();
        $this->assertNotNull($user->mfa_enabled_at);
        $this->assertSame($secret, $user->mfa_secret);
        $this->assertCount(8, $user->mfa_recovery_codes);
        $recovery = session('mfa_recovery_plain')[0];
        $this->get(route('mfa.setup'))->assertOk()->assertSee('已开启');

        // 模拟重新登录：会话里没有验证标记，业务页被拦到验证页
        $this->flushSession();
        $this->actingAs($user);
        $this->get(route('home'))->assertRedirect(route('mfa.verify'));
        $this->get(route('mfa.verify'))->assertOk()->assertSee('需要二次验证');

        $this->post(route('mfa.verify.submit'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $freshCode = $totp->codeAt($totp->base32Decode($user->mfa_secret), intdiv(time(), 30));
        $this->post(route('mfa.verify.submit'), ['code' => $freshCode])
            ->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk();

        // 恢复码：用掉一个后剩 7 个，且该码作废
        $this->flushSession();
        $this->actingAs($user);
        $this->post(route('mfa.verify.submit'), ['code' => $recovery])
            ->assertRedirect(route('home'));
        $user->refresh();
        $this->assertCount(7, $user->mfa_recovery_codes);

        // 关闭：密码 + 当前动态码
        $user->forceFill(['password' => Hash::make('TestPass123')])->save();
        $code = $totp->codeAt($totp->base32Decode($user->mfa_secret), intdiv(time(), 30));
        $this->post(route('mfa.disable'), ['password' => 'TestPass123', 'code' => $code])
            ->assertRedirect(route('mfa.setup'));
        $user->refresh();
        $this->assertNull($user->mfa_enabled_at);
        $this->assertNull($user->mfa_secret);
        $this->assertNull($user->mfa_recovery_codes);
    }
}
