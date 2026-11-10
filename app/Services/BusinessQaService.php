<?php

namespace App\Services;

use App\Models\Approval\ApprovalInstance;
use App\Models\MasterData\Material;
use App\Models\Organization\User;
use App\Models\Production\ProductionOrder;
use App\Models\Sales\SalesOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P3-5 业务问答：把"自然语言问题"映射到受权限/数据范围约束的只读统计。
 * 不调用外部大模型，先保证答案有数据出处、不越权；未命中意图时返回 null，由通用对话兜底。
 */
class BusinessQaService
{
    private const INTENTS = ['sales_summary', 'inventory_query', 'pending_approvals', 'production_progress'];

    public function answer(string $question, User $user): ?array
    {
        $q = trim($question);

        if ($this->isSales($q)) {
            return $this->salesSummary($q, $user);
        }
        if ($this->isInventory($q)) {
            return $this->inventoryQuery($q, $user);
        }
        if ($this->isApprovals($q)) {
            return $this->pendingApprovals($user);
        }
        if ($this->isProduction($q)) {
            return $this->productionProgress($user);
        }

        return null;
    }

    public function examples(): array
    {
        return [
            '本月销售额多少？',
            'ACC-RM01 还有多少库存？',
            '我有多少待审批？',
            '生产工单进度怎么样？',
        ];
    }

    private function isSales(string $q): bool
    {
        return preg_match('/销售|营收|业绩|订单额/', $q) && preg_match('/多少|汇总|合计|金额|情况|报/', $q);
    }

    private function isInventory(string $q): bool
    {
        return (bool) preg_match('/库存|存货|还有多少|可用量/', $q);
    }

    private function isApprovals(string $q): bool
    {
        return (bool) preg_match('/审批|待办|待我审/', $q);
    }

    private function isProduction(string $q): bool
    {
        return preg_match('/工单|生产|在制|排产/', $q) && preg_match('/进度|多少|情况|未完工/', $q);
    }

    private function salesSummary(string $q, User $user): array
    {
        if (! $user->can('sales.view')) {
            return $this->deny('销售');
        }

        [$start, $end, $label] = $this->range($q);
        $orders = SalesOrder::whereBetween('order_date', [$start->toDateString(), $end->toDateString()])->get();
        $amount = (float) $orders->sum('total_amount');
        $byStatus = $orders->groupBy('status')->map(fn ($rows) => $rows->count());

        $answer = "【{$label}销售汇总】\n"
            . '订单数：' . $orders->count() . " 张\n"
            . '订单金额合计：' . number_format($amount, 2) . " 元\n"
            . '按下单状态：' . ($byStatus->isEmpty() ? '无' : $byStatus->map(fn ($c, $s) => "{$s} {$c} 张")->implode('，'))
            . "\n口径：按订单日期落在 {$start->toDateString()} ~ {$end->toDateString()}，仅统计当前账号数据范围内的销售订单（含未确认草稿，不含取消）。";

        return [
            'intent' => 'sales_summary',
            'answer' => $answer,
            'facts' => ['orders' => $orders->count(), 'amount' => $amount, 'by_status' => $byStatus->toArray()],
            'link' => route('sales.orders'),
        ];
    }

    private function inventoryQuery(string $q, User $user): array
    {
        if (! $user->can('inventory.view')) {
            return $this->deny('库存');
        }
        if ($user->tenant_id) {
            return $this->tenantDenied('库存');
        }

        $code = $this->extractCode($q);
        $keyword = trim(str_replace(
            ['库存', '存货', '还有多少', '还有', '多少', '查询', '查一下', '可用量', '物料', '料号', '？', '?', '吗'],
            '',
            $q
        ));

        $materialQuery = Material::query();
        if ($code) {
            $materialQuery->where('material_code', $code);
        } elseif ($keyword !== '') {
            $materialQuery->where(function ($query) use ($keyword) {
                $query->where('material_code', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
            });
        }
        $material = $materialQuery->first();

        if (! $material) {
            return [
                'intent' => 'inventory_query',
                'answer' => "没有匹配到物料（关键词：" . ($code ?: $keyword ?: '空') . "）。请提供物料编码，例如 ACC-RM01。",
                'facts' => [],
                'link' => route('inventory.index'),
            ];
        }

        $rows = DB::table('inventory_stock')
            ->where('material_id', $material->id)
            ->selectRaw('status, SUM(qty) AS qty, SUM(reserved_qty) AS reserved_qty')
            ->groupBy('status')
            ->get();
        $available = (float) $rows->where('status', 'available')->sum('qty');
        $reserved = (float) $rows->sum('reserved_qty');

        $answer = "【物料库存 · {$material->material_code} {$material->name}】\n"
            . '可用库存：' . rtrim(rtrim(number_format($available, 2), '0'), '.') . " {$material->unit}\n"
            . '已预留：' . rtrim(rtrim(number_format($reserved, 2), '0'), '.') . " {$material->unit}\n"
            . '按状态：' . ($rows->isEmpty() ? '无库存记录' : $rows->map(fn ($r) => "{$r->status} " . rtrim(rtrim(number_format((float) $r->qty, 2), '0'), '.'))->implode('，'))
            . "\n口径：库存为全仓汇总（含质检占用/冻结状态明细），可用量仅统计 available。";

        return [
            'intent' => 'inventory_query',
            'answer' => $answer,
            'facts' => ['material_code' => $material->material_code, 'available' => $available, 'reserved' => $reserved],
            'link' => route('inventory.index'),
        ];
    }

    private function pendingApprovals(User $user): array
    {
        if ($user->tenant_id) {
            return $this->tenantDenied('审批');
        }

        $instances = app(ApprovalService::class)->pendingForUser($user->id);
        $count = $instances->count();
        $top = $instances->getCollection()->take(5)->map(
            fn ($i) => "- {$i->business_type} #{$i->business_id}（第 {$i->current_step} 步）"
        )->implode("\n");

        $answer = "【待我审批】\n共 {$count} 单待处理。" . ($top ? "\n{$top}" : '');

        return [
            'intent' => 'pending_approvals',
            'answer' => $answer,
            'facts' => ['pending' => $count],
            'link' => route('approvals.index'),
        ];
    }

    private function productionProgress(User $user): array
    {
        if (! $user->can('production.view') && ! $user->can('production.order.view')) {
            return $this->deny('生产');
        }
        if ($user->tenant_id) {
            return $this->tenantDenied('生产');
        }

        $orders = ProductionOrder::with('product')
            ->whereIn('status', ['planned', 'released', 'in_progress'])
            ->orderBy('end_date')
            ->get();
        $plan = (float) $orders->sum('plan_qty');
        $done = (float) $orders->sum('completed_qty');
        $lines = $orders->take(5)->map(
            fn ($o) => '- ' . $o->order_no . ' ' . ($o->product?->name ?? '') . '：' . $o->completed_qty . '/' . $o->plan_qty . '，交期 ' . ($o->end_date?->format('Y-m-d') ?? '-')
        )->implode("\n");

        $answer = "【生产工单进度】\n"
            . "未完工工单：{$orders->count()} 张\n"
            . '计划总数：' . rtrim(rtrim(number_format($plan, 2), '0'), '.') . "，已完工："
            . rtrim(rtrim(number_format($done, 2), '0'), '.') . "\n"
            . '完成率：' . ($plan > 0 ? number_format($done / $plan * 100, 1) . '%' : '-')
            . ($lines ? "\n最近交期：\n{$lines}" : '')
            . "\n口径：planned/released/in_progress 状态，当前账号数据范围内。";

        return [
            'intent' => 'production_progress',
            'answer' => $answer,
            'facts' => ['open_orders' => $orders->count(), 'plan_qty' => $plan, 'completed_qty' => $done],
            'link' => route('production.orders'),
        ];
    }

    private function deny(string $module): array
    {
        return [
            'intent' => 'denied',
            'answer' => "当前账号没有「{$module}」查看权限，无法查询该类数据。如需开通请联系系统管理员。",
            'facts' => ['denied' => $module],
            'link' => null,
        ];
    }

    private function tenantDenied(string $module): array
    {
        return [
            'intent' => 'tenant_scope_missing',
            'answer' => "当前账号属于租户，而「{$module}」模块尚未纳入租户隔离，为避免跨租户数据泄露，本问答暂不开放该模块。",
            'facts' => ['tenant_unscoped' => $module],
            'link' => null,
        ];
    }

    /** 日期范围：今天/本月/上月/今年，默认本月。 */
    private function range(string $q): array
    {
        $now = Carbon::now();
        if (preg_match('/今天|今日/', $q)) {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), '今天'];
        }
        if (preg_match('/上月|上个月/', $q)) {
            $start = $now->copy()->subMonthNoOverflow()->startOfMonth();

            return [$start, $start->copy()->endOfMonth(), '上月'];
        }
        if (preg_match('/今年|本年/', $q)) {
            return [$now->copy()->startOfYear(), $now->copy()->endOfYear(), '今年'];
        }

        return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), '本月'];
    }

    private function extractCode(string $q): ?string
    {
        if (preg_match('/[A-Za-z][A-Za-z0-9_-]{2,}/', $q, $m)) {
            return strtoupper($m[0]);
        }

        return null;
    }
}
