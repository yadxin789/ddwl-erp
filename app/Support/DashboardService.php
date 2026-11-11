<?php

namespace App\Support;

use App\Models\Ecom\EcomOrder;
use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\SupplierBill;
use App\Models\Inventory\InventoryStock;
use App\Models\MasterData\Customer;
use App\Models\MasterData\Material;
use App\Models\MasterData\Supplier;
use App\Models\Office\TodoItem;
use App\Models\Organization\User;
use App\Models\Production\MaterialIssueRequest;
use App\Models\Production\ProductionOrder;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Quality\InspectionRecord;
use App\Models\Rnd\RndProject;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * 岗位化工作台指标服务（S4）
 *
 * 只计算"本岗配置里出现过的指标"——未配置的指标一次 SQL 都不发，
 * 避免首页把所有业务数字算一遍再藏起来（信息泄露风险与性能浪费）。
 */
class DashboardService
{
    /**
     * 工作台图表数据源：键与 config/dashboard.php 的指标卡对齐。
     * 只有本岗配置里出现过的指标，才会去查对应业务表。
     *
     * @var array<string, array{label: string, keys: array<int, string>, model: class-string}>
     */
    private const CHART_MODULES = [
        'sales' => ['label' => '销售订单', 'keys' => ['open_orders', 'ar_total'], 'model' => SalesOrder::class],
        'purchase' => ['label' => '采购订单', 'keys' => ['open_pos', 'ap_total', 'suppliers'], 'model' => PurchaseOrder::class],
        'production' => ['label' => '生产工单', 'keys' => ['open_wos', 'pending_issue_requests'], 'model' => ProductionOrder::class],
        'quality' => ['label' => '质检记录', 'keys' => ['pending_qc'], 'model' => InspectionRecord::class],
        'rnd' => ['label' => '研发项目', 'keys' => ['my_projects'], 'model' => RndProject::class],
        'ecom' => ['label' => '电商订单', 'keys' => ['ecom_orders'], 'model' => EcomOrder::class],
    ];

    /** @var array<string, callable> */
    private array $resolvers;

    public function __construct(private NavigationService $navigation)
    {
        $userId = fn () => (int) (auth()->id() ?? 0);

        $this->resolvers = [
            'open_orders' => fn () => SalesOrder::whereNotIn('status', ['completed', 'closed', 'cancelled'])->count(),
            'open_pos' => fn () => PurchaseOrder::whereNotIn('status', ['received', 'cancelled'])->count(),
            'open_wos' => fn () => ProductionOrder::whereNotIn('status', ['completed', 'cancelled'])->count(),
            'pending_qc' => fn () => InspectionRecord::where('result', 'pending')->count(),
            'pending_issue_requests' => fn () => MaterialIssueRequest::where('status', 'pending_approval')->count(),
            'ar_total' => fn () => CustomerInvoice::where('status', '!=', 'paid')->sum(DB::raw('amount - paid_amount')),
            'ap_total' => fn () => SupplierBill::where('status', '!=', 'paid')->sum(DB::raw('amount - paid_amount')),
            'stock_qty' => fn () => InventoryStock::sum('qty'),
            'materials' => fn () => Material::count(),
            'suppliers' => fn () => Supplier::count(),
            'customers' => fn () => Customer::count(),
            'ecom_orders' => fn () => EcomOrder::count(),
            'my_projects' => fn () => RndProject::withoutGlobalScope('data_scope')
                ->where(function ($q) use ($userId) {
                    $q->where('creator_id', $userId())
                        ->orWhereHas('tasks', fn ($t) => $t->where('executor_id', $userId()));
                })->count(),
            'my_todos' => fn () => TodoItem::where('user_id', $userId())->where('is_finished', false)->count(),
            'sys_users' => fn () => User::count(),
            'sys_roles' => fn () => Role::count(),
            // P1-02：标准成本口径的期间毛利与采购价差（只给财务/总经理看）
            'gross_profit_period' => fn () => round((float) DB::table('sales_order_lines as l')
                ->join('sales_orders as o', 'o.id', '=', 'l.order_id')
                ->whereNotNull('l.std_cost_amount')
                ->whereBetween(DB::raw('DATE(o.order_date)'), [now()->startOfMonth()->toDateString(), now()->toDateString()])
                ->selectRaw('SUM(l.shipped_qty * l.unit_price) - SUM(l.std_cost_amount) as gp')
                ->value('gp'), 2),
            'price_variance_period' => fn () => round((float) DB::table('inventory_transactions')
                ->whereNotNull('price_variance')
                ->whereBetween(DB::raw('DATE(created_at)'), [now()->startOfMonth()->toDateString(), now()->toDateString()])
                ->sum('price_variance'), 2),
        ];
    }

    /**
     * 按显式指标键计算卡片（经营驾驶舱等只读页复用）。
     *
     * @param  array<int, string>  $keys
     * @return array<int, array>
     */
    public function cardsForKeys(array $keys): array
    {
        $config = config('dashboard', []);
        $cards = [];

        foreach (array_unique($keys) as $key) {
            $definition = $config['cards'][$key] ?? null;
            if (! $definition || ! isset($this->resolvers[$key])) {
                continue;
            }

            $cards[] = $definition + [
                'key' => $key,
                'value' => ($this->resolvers[$key])(),
                'url' => (isset($definition['route']) && \Illuminate\Support\Facades\Route::has($definition['route']))
                    ? route($definition['route'])
                    : null,
            ];
        }

        return $cards;
    }

    /**
     * @return array{position_name:?string, department:?string, cards: array<int, array>, quick_links: array<int, array>, no_position: bool}
     */
    public function forCurrentUser(): array
    {
        $nav = $this->navigation->forCurrentUser();
        $config = config('dashboard', []);

        $keys = [];
        foreach ($nav['position_keys'] as $positionKey) {
            foreach ($config['positions'][$positionKey] ?? [] as $metricKey) {
                $keys[$metricKey] = true;
            }
        }

        // 无岗位（含管理员以外的未绑定账号）：只给兜底指标，不显示任何业务数字。
        if (! $keys) {
            foreach ($config['default'] ?? [] as $metricKey) {
                $keys[$metricKey] = true;
            }
        }

        $keys = array_keys($keys);
        $cards = [];
        foreach ($keys as $key) {
            $definition = $config['cards'][$key] ?? null;
            if (! $definition || ! isset($this->resolvers[$key])) {
                continue;
            }

            $value = ($this->resolvers[$key])();
            $cards[] = $definition + [
                'key' => $key,
                'value' => $value,
                'url' => (isset($definition['route']) && \Illuminate\Support\Facades\Route::has($definition['route']))
                    ? route($definition['route'])
                    : null,
            ];
        }

        return [
            'position_name' => $nav['position_name'],
            'department' => $nav['department'],
            'no_position' => $nav['no_position'],
            'cards' => $cards,
            'charts' => $this->chartsForKeys($keys),
            // 快捷入口复用侧边栏的业务项（已做过权限与路由校验）
            'quick_links' => array_slice($nav['business'], 0, 6),
        ];
    }

    /**
     * 近 6 个月业务量趋势：按本岗可看的模块生成折线序列。
     * 模型自带的数据范围/租户 scope 继续生效，不会越权聚合。
     *
     * @param  array<int, string>  $keys
     * @return array{months: array<int, string>, series: array<int, array{name: string, data: array<int, int>}>}
     */
    public function chartsForKeys(array $keys): array
    {
        $months = [];
        $cursor = now()->startOfMonth()->subMonths(5);

        for ($i = 0; $i < 6; $i++) {
            $months[] = [
                'label' => $cursor->format('m月'),
                'from' => $cursor->copy()->startOfMonth()->toDateTimeString(),
                'to' => $cursor->copy()->addMonth()->startOfMonth()->toDateTimeString(),
            ];
            $cursor->addMonth();
        }

        $series = [];
        foreach (self::CHART_MODULES as $module) {
            if (! array_intersect($module['keys'], $keys)) {
                continue;
            }

            $data = [];
            foreach ($months as $month) {
                $data[] = $module['model']::query()
                    ->where('created_at', '>=', $month['from'])
                    ->where('created_at', '<', $month['to'])
                    ->count();
            }

            $series[] = ['name' => $module['label'], 'data' => $data];
        }

        return [
            'months' => array_column($months, 'label'),
            'series' => $series,
        ];
    }
}
