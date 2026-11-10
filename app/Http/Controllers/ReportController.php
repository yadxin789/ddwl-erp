<?php

namespace App\Http\Controllers;

use App\Models\Finance\CustomerInvoice;
use App\Models\Finance\SupplierBill;
use App\Models\MasterData\Supplier;
use App\Models\Organization\Department;
use App\Models\Purchasing\GoodsReceipt;
use App\Models\Quality\Defect;
use App\Models\Quality\InspectionRecord;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use App\Support\DataScopeService;
use Illuminate\Support\Facades\DB;

/**
 * 报表中心与四张岗位报表（G4 / G7）
 *
 * 口径（v1.3 §4.29 / D-10）：收入按**开票**确认，不用订单额代替收入。
 * P0-08：全部报表走行级范围（模型全局 scope 生效），页头明示当前数据范围；
 *        访问权限由 report.view 拆为 report.sales / quality / supplier / finance 四条。
 */
class ReportController extends Controller
{
    /** 当前账号的报表数据范围文案（页头展示，避免"看到数但不知道口径"） */
    private function scopeLabel(): string
    {
        $service = app(DataScopeService::class);
        $deptName = Department::query()->find($service->departmentId())?->dept_name;

        return match ($service->forCurrentUser()) {
            DataScopeService::ALL => '全部业务数据',
            DataScopeService::DEPARTMENT => '本部门（' . ($deptName ?? '未设置部门') . '）',
            DataScopeService::NONE => '无业务数据（系统管理岗）',
            default => '仅本人负责的数据',
        };
    }

    public function center()
    {
        $cards = [
            ['name' => '销售分析', 'route' => 'report.sales', 'desc' => '开票收入 / 客户贡献 / 订单状态', 'icon' => 'fa-chart-line'],
            ['name' => '质量报表', 'route' => 'report.quality', 'desc' => '检验合格率 / 缺陷 TOP', 'icon' => 'fa-shield-halved'],
            ['name' => '供应商绩效', 'route' => 'report.supplier', 'desc' => '到货笔数 / 收货金额', 'icon' => 'fa-industry'],
            ['name' => '财务报表', 'route' => 'report.finance', 'desc' => '开票收入 / 应收应付 / 账龄', 'icon' => 'fa-coins'],
            ['name' => '经营驾驶舱', 'route' => 'dashboard.exec', 'desc' => '老板只读汇总', 'icon' => 'fa-gauge-high'],
        ];

        return view('reports.center', compact('cards'));
    }

    public function sales()
    {
        // 开票口径收入
        $invoicedTotal = (float) CustomerInvoice::sum('amount');
        $invoicedPaid = (float) CustomerInvoice::sum('paid_amount');

        $topCustomers = CustomerInvoice::query()
            ->join('customers', 'customers.id', '=', 'customer_invoices.customer_id')
            ->select('customers.customer_name', DB::raw('SUM(customer_invoices.amount) as invoiced'))
            ->groupBy('customers.customer_name')
            ->orderByDesc('invoiced')
            ->limit(10)
            ->get();

        $ordersByStatus = SalesOrder::query()
            ->select('status', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(total_amount) as amount'))
            ->groupBy('status')
            ->get();

        $topProducts = SalesOrderLine::query()
            ->whereHas('order')
            ->join('materials', 'materials.id', '=', 'sales_order_lines.material_id')
            ->select('materials.material_code', 'materials.name', DB::raw('SUM(sales_order_lines.qty) as qty'))
            ->groupBy('materials.material_code', 'materials.name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get();

        $scopeLabel = $this->scopeLabel();

        return view('reports.sales', compact('invoicedTotal', 'invoicedPaid', 'topCustomers', 'ordersByStatus', 'topProducts', 'scopeLabel'));
    }

    public function quality()
    {
        $total = (int) InspectionRecord::count();
        $qualified = (int) InspectionRecord::sum('qualified_qty');
        $defective = (int) InspectionRecord::sum('defect_qty');
        $sample = (int) InspectionRecord::sum('sample_qty');
        $passRate = $sample > 0 ? round($qualified / $sample * 100, 2) : null;

        $byStage = InspectionRecord::query()
            ->select('stage', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(qualified_qty) as ok'), DB::raw('SUM(defect_qty) as ng'))
            ->groupBy('stage')
            ->get();

        $topDefects = InspectionRecord::query()
            ->whereNotNull('remark')
            ->select('remark', DB::raw('COUNT(*) as cnt'))
            ->groupBy('remark')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        $defectCodes = Defect::query()->orderBy('code')->limit(50)->get(['code', 'name']);

        $scopeLabel = $this->scopeLabel();

        return view('reports.quality', compact('total', 'qualified', 'defective', 'sample', 'passRate', 'byStage', 'topDefects', 'defectCodes', 'scopeLabel'));
    }

    public function supplier()
    {
        $receipts = GoodsReceipt::query()->count();
        $receiptQty = (float) GoodsReceipt::query()->sum('total_qty');

        $topSuppliers = SupplierBill::query()
            ->join('suppliers', 'suppliers.id', '=', 'supplier_bills.supplier_id')
            ->select('suppliers.supplier_name', DB::raw('SUM(supplier_bills.amount) as billed'))
            ->groupBy('suppliers.supplier_name')
            ->orderByDesc('billed')
            ->limit(10)
            ->get();

        $supplierCount = Supplier::count();

        $scopeLabel = $this->scopeLabel();

        return view('reports.supplier', compact('receipts', 'receiptQty', 'topSuppliers', 'supplierCount', 'scopeLabel'));
    }

    public function finance()
    {
        $invoiced = (float) CustomerInvoice::sum('amount');
        $collected = (float) CustomerInvoice::sum('paid_amount');
        $ar = (float) CustomerInvoice::where('status', '!=', 'paid')->sum(DB::raw('amount - paid_amount'));

        $billed = (float) SupplierBill::sum('amount');
        $paid = (float) SupplierBill::sum('paid_amount');
        $ap = (float) SupplierBill::where('status', '!=', 'paid')->sum(DB::raw('amount - paid_amount'));

        $scopeLabel = $this->scopeLabel();

        return view('reports.finance', compact('invoiced', 'collected', 'ar', 'billed', 'paid', 'ap', 'scopeLabel'));
    }
}
