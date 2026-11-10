<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevopsController extends Controller
{
    public function index()
    {
        // P0 修复：不再暴露 PHP 版本 / DB 名 / APP_ENV / debug 开关等敏感信息
        $info = [
            '应用名称' => config('app.name'),
            '运行环境' => app()->environment() === 'local' ? '开发环境' : '生产环境',
            '时区' => config('app.timezone'),
            '缓存驱动' => config('cache.default'),
        ];

        try {
            DB::select('select 1 as ok');
            $dbStatus = '✅ 连接正常';
        } catch (\DomainException|\InvalidArgumentException $e) {
            $dbStatus = '❌ 连接失败';
            Log::error('Devops 页面报告数据库连接失败', ['err' => $e->getMessage()]);
        }

        $tables = count(DB::select('SHOW TABLES'));
        $migrations = DB::table('migrations')->count();

        return view('devops.index', compact('info', 'dbStatus', 'tables', 'migrations'));
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');

        Log::info('管理员清理了应用缓存', ['user_id' => auth()->id()]);

        return back()->with('success', '缓存已清理。');
    }
}
