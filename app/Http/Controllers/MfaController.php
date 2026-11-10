<?php

namespace App\Http\Controllers;

use App\Models\Security\SecurityEvent;
use App\Support\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * P3-3 用户自助 MFA/TOTP：开启、验证、恢复码、关闭。
 * 不提供管理员重置（见执行记录"边界"）。
 */
class MfaController extends Controller
{
    public function __construct(private TotpService $totp)
    {
    }

    public function setup(Request $request)
    {
        $user = $request->user();
        if ($user->mfa_enabled_at) {
            return view('security.mfa', [
                'enabled' => true,
                'user' => $user,
                'secret' => null,
                'uri' => null,
                'recoveryPlain' => session('mfa_recovery_plain', []),
            ]);
        }

        $secret = $request->session()->get('mfa_setup_secret');
        if (! $secret) {
            $secret = $this->totp->generateSecret();
            $request->session()->put('mfa_setup_secret', $secret);
        }

        return view('security.mfa', [
            'enabled' => false,
            'user' => $user,
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $user->username),
            'recoveryPlain' => [],
        ]);
    }

    public function enable(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:8']]);
        $user = $request->user();
        if ($user->mfa_enabled_at) {
            return back()->withErrors(['code' => '二次验证已开启，无需重复设置。']);
        }

        $secret = $request->session()->get('mfa_setup_secret');
        if (! $secret) {
            return redirect()->route('mfa.setup')->withErrors(['code' => '设置已过期，请重新开始。']);
        }
        if (! $this->totp->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => '验证码不正确，请确认手机时间正确后重试。']);
        }

        $plain = $this->totp->recoveryCodes();
        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_recovery_codes' => array_map(fn ($c) => Hash::make($c), $plain),
            'mfa_enabled_at' => now(),
            'mfa_last_verified_at' => now(),
        ])->save();

        $request->session()->forget('mfa_setup_secret');
        $request->session()->put('mfa_verified_at', now()->timestamp);
        $request->session()->put('mfa_user_id', $user->id);

        return redirect()->route('mfa.setup')
            ->with('success', '二次验证已开启。')
            ->with('mfa_recovery_plain', $plain);
    }

    public function verifyForm(Request $request)
    {
        if (! $request->user()->mfa_enabled_at) {
            return redirect()->route('mfa.setup');
        }
        if ($this->alreadyVerified($request)) {
            return redirect()->route('home');
        }

        return view('security.mfa-verify');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $request->user();
        if (! $user->mfa_enabled_at) {
            return redirect()->route('mfa.setup');
        }

        $key = 'mfa-verify:' . $user->id . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => '验证失败次数过多，请 1 分钟后再试。']);
        }

        $input = (string) $request->input('code');
        $ok = $this->totp->verify($user->mfa_secret, $input);
        $usedRecovery = false;

        if (! $ok) {
            $candidate = $this->totp->normalizeRecovery($input);
            $codes = $user->mfa_recovery_codes ?? [];
            foreach ($codes as $index => $hash) {
                if (Hash::check($candidate, $hash)) {
                    unset($codes[$index]);
                    $user->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();
                    $ok = true;
                    $usedRecovery = true;
                    break;
                }
            }
        }

        if (! $ok) {
            RateLimiter::hit($key, 60);
            if (RateLimiter::attempts($key) >= 5) {
                SecurityEvent::create([
                    'event_type' => 'mfa_failed',
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'detail' => "用户 {$user->username} 二次验证连续失败",
                    'level' => 'high',
                    'created_at' => now(),
                ]);
            }

            return back()->withErrors(['code' => '验证码或恢复码不正确。']);
        }

        RateLimiter::clear($key);
        $request->session()->put('mfa_verified_at', now()->timestamp);
        $request->session()->put('mfa_user_id', $user->id);
        $user->forceFill(['mfa_last_verified_at' => now()])->save();

        if ($usedRecovery) {
            return redirect()->intended(route('home'))->with('success', '已使用恢复码登录，该恢复码已作废。');
        }

        return redirect()->intended(route('home'));
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:20'],
        ]);
        $user = $request->user();
        if (! Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors(['password' => '登录密码不正确。']);
        }
        if (! $this->totp->verify($user->mfa_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => '验证码不正确，无法关闭二次验证。']);
        }

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_enabled_at' => null,
            'mfa_last_verified_at' => null,
        ])->save();

        $request->session()->forget(['mfa_verified_at', 'mfa_user_id', 'mfa_setup_secret', 'mfa_recovery_plain']);

        return redirect()->route('mfa.setup')->with('success', '二次验证已关闭。');
    }

    private function alreadyVerified(Request $request): bool
    {
        $userId = $request->session()->get('mfa_user_id');

        return $userId && (int) $userId === (int) $request->user()->id
            && $request->session()->get('mfa_verified_at');
    }
}
