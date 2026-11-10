<?php
/**
 * 现状审计探针（配合 docs/整改完善计划-v1.4.md 使用）
 *
 * 作用：不读代码也能拿到"导航可达性 / 共享路由 / 归属列 / 审批与集成数据量"的当前事实。
 * 运行：在项目根目录执行
 *   php docs/tools/audit-probe.php
 * 或
 *   C:\xampp\php\php.exe docs/tools/audit-probe.php
 */

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

echo "== 1. menu_items 中映射到带参路由（导航必然跳过，等于入口失效） ==\n";
foreach (config('navigation.menu_items') as $name => $item) {
    $r = $item['route'] ?? null;
    if (! $r) { echo "  [无路由] {$name}\n"; continue; }
    if (! Route::has($r)) { echo "  [路由不存在] {$name} => {$r}\n"; continue; }
    $route = Route::getRoutes()->getByName($r);
    $params = $route->parameterNames();
    if ($params) {
        echo "  {$name} => {$r} (参数: ".implode(',', $params).")\n";
    }
}

echo "\n== 2. 仅 auth、无 permission 中间件的路由（共享入口，行级权限须控制器自证） ==\n";
foreach (Route::getRoutes() as $route) {
    $mw = $route->gatherMiddleware();
    $hasAuth = false; $hasPerm = false;
    foreach ($mw as $m) {
        if (is_string($m) && str_starts_with($m, 'auth')) { $hasAuth = true; }
        if (is_string($m) && str_starts_with($m, 'permission')) { $hasPerm = true; }
    }
    if ($hasAuth && ! $hasPerm && $route->getName()) {
        echo "  ".implode('|', $route->methods())." ".$route->uri()." => ".$route->getName()."\n";
    }
}

echo "\n== 3. 审批流与待审批数据 ==\n";
foreach (\App\Models\Approval\ApprovalFlow::all(['id', 'business_type', 'status']) as $f) {
    echo "  flow#{$f->id} {$f->business_type} status=".($f->status ? 'on' : 'off')."\n";
}
echo "  approval_instances: ".\App\Models\Approval\ApprovalInstance::count()."\n";

echo "\n== 4. 事件/集成/审批孤岛数据量 ==\n";
echo "  integration_outbox: ".\App\Models\Event\IntegrationOutbox::count()."\n";
echo "  domain_events: ".\App\Models\Event\DomainEvent::count()."\n";
echo "  rnd_extension_applications: ".\App\Models\Rnd\RndExtensionApplication::count()."\n";
echo "  sync_tasks: ".\App\Models\Sync\SyncTask::count()."\n";

echo "\n== 5. 关键业务表是否具备归属列 ==\n";
$checks = [
    'production_orders' => ['department_id', 'created_by', 'creator_id', 'workshop_id', 'line_id'],
    'customers' => ['owner_id', 'salesperson_id', 'department_id'],
    'suppliers' => ['owner_id', 'buyer_id', 'department_id'],
    'inventory_stock' => ['warehouse_id', 'department_id', 'owner_id'],
    'goods_receipts' => ['receiver_id', 'department_id'],
    'customer_invoices' => ['owner_id', 'salesperson_id', 'department_id'],
    'supplier_bills' => ['owner_id', 'buyer_id', 'department_id'],
    'production_operation_logs' => ['worker_id', 'department_id'],
];
foreach ($checks as $table => $cols) {
    if (! Schema::hasTable($table)) { echo "  {$table}: 表不存在\n"; continue; }
    $have = [];
    foreach ($cols as $c) { if (Schema::hasColumn($table, $c)) { $have[] = $c; } }
    echo "  {$table}: ".(($have ? implode(',', $have) : '（无归属列）'))."\n";
}

echo "\n== 6. 规模 ==\n";
echo "  路由总数: ".count(Route::getRoutes())."\n";
$tables = collect(\DB::select('show tables'))->map(fn ($r) => array_values((array) $r)[0]);
echo "  数据表: ".$tables->count()."\n";

echo "\n== 7. 关键业务表数据量（判断是否有可验收的真实数据） ==\n";
foreach (['users', 'positions', 'roles', 'permissions', 'sales_orders', 'purchase_orders', 'production_orders', 'inventory_stock', 'goods_receipts', 'inspection_records', 'customer_invoices', 'payments', 'rnd_projects', 'rnd_project_tasks', 'approval_flows', 'ecom_orders', 'crm_leads'] as $t) {
    if (! Schema::hasTable($t)) { echo "  {$t}: 表不存在\n"; continue; }
    echo "  {$t}: ".\DB::table($t)->count()."\n";
}
