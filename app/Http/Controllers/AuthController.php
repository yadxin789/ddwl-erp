<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\Organization\User;
use App\Models\Security\LoginFailure;
use App\Models\Security\LoginLog;
use App\Models\Security\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * 显示登录页
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * 登录处理：账号（username/email）+ 密码，禁用账号禁止登录
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        $ip = $request->ip();
        $ua = substr($request->userAgent() ?? '', 0, 255);

        // 0. 检查该账号+IP是否已被锁定
        $failure = LoginFailure::where('username', $credentials['account'])
            ->where('ip_address', $ip)
            ->first();
        if ($failure && $failure->locked_until && $failure->locked_until->isFuture()) {
            throw ValidationException::withMessages([
                'account' => "失败次数过多，账号已锁定至 {$failure->locked_until->format('H:i')}，请稍后再试。",
            ]);
        }

        $user = User::where('username', $credentials['account'])
            ->orWhere('email', $credentials['account'])
            ->first();

        if (! $user || $user->status !== 1 || ! Hash::check($credentials['password'], $user->password)) {
            // 累计失败次数
            $failure = LoginFailure::updateOrCreate(
                ['username' => $credentials['account'], 'ip_address' => $ip],
                ['fail_count' => ($failure->fail_count ?? 0) + 1]
            );
            // 满5次锁30分钟
            if ($failure->fail_count >= 5) {
                $failure->update(['locked_until' => now()->addMinutes(30)]);
                SecurityEvent::create([
                    'event_type' => 'account_locked', 'user_id' => $user->id ?? null,
                    'ip_address' => $ip, 'detail' => "账号 {$credentials['account']} 连续失败{$failure->fail_count}次，锁定30分钟",
                    'level' => 'high', 'created_at' => now(),
                ]);
            }

            LoginLog::create([
                'user_id' => $user->id ?? null,
                'username' => $credentials['account'],
                'ip_address' => $ip, 'user_agent' => $ua,
                'status' => 'failed', 'fail_reason' => '账号或密码错误',
                'created_at' => now(),
            ]);
            throw ValidationException::withMessages([
                'account' => '账号或密码错误，或账号已被禁用。',
            ]);
        }

        Auth::login($user, $request->boolean('remember'));

        // P3-3：每次登录都重新过二次验证，避免旧会话标记沿用
        $request->session()->forget(['mfa_verified_at', 'mfa_user_id', 'mfa_setup_secret']);

        // 登录成功：清除失败计数
        LoginFailure::where('username', $credentials['account'])->where('ip_address', $ip)->delete();

        LoginLog::create([
            'user_id' => $user->id,
            'username' => $user->username,
            'ip_address' => $ip, 'user_agent' => $ua,
            'status' => 'success', 'created_at' => now(),
        ]);

        // 安全事件：非常用IP登录（与上次登录IP不同）
        if ($user->last_login_ip && $user->last_login_ip !== $ip) {
            SecurityEvent::create([
                'event_type' => 'unusual_ip', 'user_id' => $user->id,
                'ip_address' => $ip,
                'detail' => "用户 {$user->username} 从新IP {$ip} 登录（上次 {$user->last_login_ip}）",
                'level' => 'medium', 'created_at' => now(),
            ]);
        }
        // 凌晨登录（0-5点）告警
        if (now()->hour >= 0 && now()->hour < 5) {
            SecurityEvent::create([
                'event_type' => 'late_night_login', 'user_id' => $user->id,
                'ip_address' => $ip,
                'detail' => "用户 {$user->username} 凌晨 {$user->created_at->format('H:i')} 登录",
                'level' => 'low', 'created_at' => now(),
            ]);
        }

        // 记录最后登录信息
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /**
     * 登出
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * 修改密码
     */
    public function updatePassword(UpdatePasswordRequest $request)
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = Auth::user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => '当前密码不正确。',
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', '密码修改成功，请牢记新密码。');
    }
}
