<?php

namespace App\Http\Controllers;

use App\Support\DashboardService;
use Illuminate\Support\Facades\Route;

/**
 * 工作台（S4）：指标由本岗配置决定，不再"一次算 10 个指标给所有人"。
 */
class HomeController extends Controller
{
    public function index(DashboardService $dashboard)
    {
        return view('home', ['dashboard' => $dashboard->forCurrentUser()]);
    }

    /**
     * 经营驾驶舱（G14）：老板只读汇总，一张页面看全公司，不提供任何写操作。
     */
    public function exec(DashboardService $dashboard)
    {
        $cards = $dashboard->cardsForKeys([
            'open_orders', 'open_pos', 'open_wos', 'pending_qc',
            'ar_total', 'ap_total', 'stock_qty', 'materials',
        ]);

        $readonlyReports = collect([
            ['name' => '销售分析', 'route' => 'report.sales'],
            ['name' => '质量报表', 'route' => 'report.quality'],
            ['name' => '供应商绩效', 'route' => 'report.supplier'],
            ['name' => '财务报表', 'route' => 'report.finance'],
            ['name' => '报表中心', 'route' => 'report.center'],
        ])->filter(fn ($item) => Route::has($item['route']))->values();

        return view('dashboard.exec', compact('cards', 'readonlyReports'));
    }
}
