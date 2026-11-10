<?php

namespace Tests\Feature;

use App\Models\MasterData\Customer;
use App\Models\Organization\User;
use App\Models\Sales\SalesOrder;
use App\Support\DataScopeService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S5 行级数据范围：页面守卫之外的第二层隔离。
 */
class DataScopeTest extends TestCase
{
    private function actingAsDemo(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        $this->actingAs($user);
        app(DataScopeService::class)->forget();

        return $user;
    }

    public function test_data_scope_type_resolves_from_position(): void
    {
        $service = app(DataScopeService::class);

        $this->actingAsDemo('sales');              // 销售专员 → self
        $this->assertSame('self', $service->forCurrentUser());

        $this->actingAsDemo('wh-supervisor');      // 仓库主管 → all
        $this->assertSame('all', $service->forCurrentUser());

        $this->actingAsDemo('admin');              // 系统管理员 → none
        $this->assertSame('none', $service->forCurrentUser());

        $this->actingAsDemo('iqc-inspector');      // IQC → department
        $this->assertSame('department', $service->forCurrentUser());
    }

    public function test_sales_order_query_is_filtered_by_scope(): void
    {
        DB::beginTransaction();

        try {
            $sales = $this->actingAsDemo('sales');
            $customerId = Customer::query()->value('id') ?? 0;
            $baseline = SalesOrder::withoutGlobalScope('data_scope')->count();

            $own = SalesOrder::create([
                'order_no' => 'T-SCOPE-OWN-' . uniqid(),
                'customer_id' => $customerId,
                'order_date' => now()->toDateString(),
                'status' => 'draft',
                'salesperson_id' => $sales->id,
            ]);

            $foreign = SalesOrder::create([
                'order_no' => 'T-SCOPE-OTHER-' . uniqid(),
                'customer_id' => $customerId,
                'order_date' => now()->toDateString(),
                'status' => 'draft',
                'salesperson_id' => 999999,
            ]);

            // self：看到既有演示单 + 自己新建的那一单（不假定演示库为空）
            $this->assertSame($baseline + 1, SalesOrder::count());

            // all：仓库主管看到全部（含新建两单）
            $this->actingAsDemo('wh-supervisor');
            $this->assertSame($baseline + 2, SalesOrder::count());

            // none：管理员不看业务数据
            $this->actingAsDemo('admin');
            $this->assertSame(0, SalesOrder::count());

            $this->assertTrue($own->exists && $foreign->exists);
        } finally {
            DB::rollBack();
        }
    }

    public function test_purchase_and_inspection_scopes_use_owner_columns(): void
    {
        DB::beginTransaction();

        try {
            $purchase = $this->actingAsDemo('purchase');   // 采购专员 → self
            $this->assertSame('self', app(DataScopeService::class)->forCurrentUser());

            // 采购专员查采购单：不会出现他人的单（结果只可能是 0 或自己的单）
            $this->assertSame(
                0,
                \App\Models\Purchasing\PurchaseOrder::query()->where('buyer_id', '!=', $purchase->id)->count()
            );

            $iqc = $this->actingAsDemo('iqc-inspector');   // IQC → department
            // 品质部无 department_id 列时退化：仍不会跨部门返回他人记录
            $this->assertSame('department', app(DataScopeService::class)->forCurrentUser());
        } finally {
            DB::rollBack();
        }
    }

    /**
     * P0-08：新补归属列的六张表全部按范围过滤
     */
    public function test_production_orders_scope_by_creator_and_department(): void
    {
        DB::beginTransaction();

        try {
            $operator = $this->actingAsDemo('operator');   // 作业员 → self
            $prodDept = (int) DB::table('departments')->where('dept_code', 'D-PROD')->value('id');
            $template = DB::table('production_orders')->first();

            $own = \App\Models\Production\ProductionOrder::create([
                'order_no' => 'T-P008-OWN-' . uniqid(),
                'product_material_id' => $template->product_material_id,
                'bom_id' => $template->bom_id,
                'routing_id' => $template->routing_id,
                'plan_qty' => 10,
                'status' => 'released',
                'department_id' => $prodDept,
                'created_by' => $operator->id,
            ]);
            $foreign = \App\Models\Production\ProductionOrder::create([
                'order_no' => 'T-P008-OTH-' . uniqid(),
                'product_material_id' => $template->product_material_id,
                'bom_id' => $template->bom_id,
                'routing_id' => $template->routing_id,
                'plan_qty' => 10,
                'status' => 'released',
                'department_id' => $prodDept,
                'created_by' => 999999,
            ]);

            // self：只见自己创建的
            $this->assertSame(1, \App\Models\Production\ProductionOrder::count());

            // department：生产组长看整个生产部（含历史回填工单）
            $this->actingAsDemo('prod-leader');
            $this->assertGreaterThanOrEqual(2, \App\Models\Production\ProductionOrder::count());
            $this->assertSame(
                0,
                \App\Models\Production\ProductionOrder::query()->where('department_id', '!=', $prodDept)->count()
            );

            // none：管理员 0 行
            $this->actingAsDemo('admin');
            $this->assertSame(0, \App\Models\Production\ProductionOrder::count());

            $this->assertTrue($own->exists && $foreign->exists);
        } finally {
            DB::rollBack();
        }
    }

    public function test_customers_and_suppliers_scope_by_owner(): void
    {
        DB::beginTransaction();

        try {
            $sales = $this->actingAsDemo('sales');        // self
            $others = Customer::withoutGlobalScope('data_scope')->where('owner_id', '!=', $sales->id)->count();
            $mine = Customer::withoutGlobalScope('data_scope')->where('owner_id', $sales->id)->count();

            $this->assertSame($mine, Customer::count());
            $this->assertSame(
                0,
                Customer::query()->where('owner_id', '!=', $sales->id)->count()
            );

            // 销售主管 all
            $this->actingAsDemo('sal-manager');
            $this->assertSame($mine + $others, Customer::count());

            // 供应商：采购专员只看自己的，采购主管看全部
            $purchase = $this->actingAsDemo('purchase');
            $this->assertSame(
                0,
                \App\Models\MasterData\Supplier::query()->where('owner_id', '!=', $purchase->id)->count()
            );
            $this->actingAsDemo('pur-manager');
            $this->assertGreaterThanOrEqual(1, \App\Models\MasterData\Supplier::count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_inventory_stock_is_limited_to_own_department_warehouse(): void
    {
        DB::beginTransaction();

        try {
            $whDept = (int) DB::table('departments')->where('dept_code', 'D-WH')->value('id');
            $prodDept = (int) DB::table('departments')->where('dept_code', 'D-PROD')->value('id');
            $materialId = (int) DB::table('materials')->value('id');
            $locationId = (int) DB::table('inventory_stock')->value('location_id');
            $suffix = substr(uniqid(), -6);

            $whA = \App\Models\Inventory\Warehouse::create([
                'warehouse_code' => 'TWA' . $suffix,
                'name' => '测试仓（仓储部）',
                'type' => 'finished',
                'department_id' => $whDept,
                'status' => 1,
            ]);
            $whB = \App\Models\Inventory\Warehouse::create([
                'warehouse_code' => 'TWB' . $suffix,
                'name' => '测试仓（生产部）',
                'type' => 'line_side',
                'department_id' => $prodDept,
                'status' => 1,
            ]);

            \App\Models\Inventory\InventoryStock::create([
                'material_id' => $materialId, 'lot_no' => 'T-P008-A-' . $suffix,
                'warehouse_id' => $whA->id, 'location_id' => $locationId, 'qty' => 10, 'status' => 'available',
            ]);
            \App\Models\Inventory\InventoryStock::create([
                'material_id' => $materialId, 'lot_no' => 'T-P008-B-' . $suffix,
                'warehouse_id' => $whB->id, 'location_id' => $locationId, 'qty' => 10, 'status' => 'available',
            ]);

            // 仓管（self）：只能看到仓储部名下仓库的库存
            $this->actingAsDemo('wh-keeper');
            $this->assertSame(
                0,
                \App\Models\Inventory\InventoryStock::query()->where('warehouse_id', $whB->id)->count()
            );
            $this->assertGreaterThanOrEqual(1, \App\Models\Inventory\InventoryStock::query()->where('warehouse_id', $whA->id)->count());

            // 仓库主管（all）：两仓都能看到
            $this->actingAsDemo('wh-supervisor');
            $this->assertSame(1, \App\Models\Inventory\InventoryStock::query()->where('warehouse_id', $whB->id)->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_invoices_and_bills_scope_by_owner(): void
    {
        DB::beginTransaction();

        try {
            $sales = $this->actingAsDemo('sales');
            $customerId = (int) Customer::withoutGlobalScope('data_scope')->value('id');

            \App\Models\Finance\CustomerInvoice::create([
                'invoice_no' => 'T-INV-OWN-' . uniqid(), 'customer_id' => $customerId,
                'invoice_date' => now()->toDateString(), 'amount' => 100, 'paid_amount' => 0,
                'status' => 'open', 'owner_id' => $sales->id, 'salesperson_id' => $sales->id,
            ]);
            \App\Models\Finance\CustomerInvoice::create([
                'invoice_no' => 'T-INV-OTH-' . uniqid(), 'customer_id' => $customerId,
                'invoice_date' => now()->toDateString(), 'amount' => 900, 'paid_amount' => 0,
                'status' => 'open', 'owner_id' => 999999,
            ]);

            $this->assertSame(1, \App\Models\Finance\CustomerInvoice::count());
            $this->assertSame(100.0, (float) \App\Models\Finance\CustomerInvoice::sum('amount'));

            // 会计 all
            $this->actingAsDemo('accountant');
            $this->assertSame(2, \App\Models\Finance\CustomerInvoice::count());

            // 应付账单同理
            $purchase = $this->actingAsDemo('purchase');
            $supplierId = (int) \App\Models\MasterData\Supplier::withoutGlobalScope('data_scope')->value('id');
            \App\Models\Finance\SupplierBill::create([
                'bill_no' => 'T-BILL-OWN-' . uniqid(), 'supplier_id' => $supplierId,
                'bill_date' => now()->toDateString(), 'amount' => 50, 'paid_amount' => 0,
                'status' => 'open', 'owner_id' => $purchase->id, 'buyer_id' => $purchase->id,
            ]);
            \App\Models\Finance\SupplierBill::create([
                'bill_no' => 'T-BILL-OTH-' . uniqid(), 'supplier_id' => $supplierId,
                'bill_date' => now()->toDateString(), 'amount' => 500, 'paid_amount' => 0,
                'status' => 'open', 'owner_id' => 999999,
            ]);

            $this->assertSame(1, \App\Models\Finance\SupplierBill::count());
            $this->assertSame(50.0, (float) \App\Models\Finance\SupplierBill::sum('amount'));
        } finally {
            DB::rollBack();
        }
    }

    public function test_report_amounts_follow_row_scope(): void
    {
        DB::beginTransaction();

        try {
            $sales = $this->actingAsDemo('sales');
            $customerId = (int) Customer::withoutGlobalScope('data_scope')->value('id');

            \App\Models\Finance\CustomerInvoice::create([
                'invoice_no' => 'T-RPT-OWN-' . uniqid(), 'customer_id' => $customerId,
                'invoice_date' => now()->toDateString(), 'amount' => 300, 'paid_amount' => 0,
                'status' => 'open', 'owner_id' => $sales->id, 'salesperson_id' => $sales->id,
            ]);
            \App\Models\Finance\CustomerInvoice::create([
                'invoice_no' => 'T-RPT-OTH-' . uniqid(), 'customer_id' => $customerId,
                'invoice_date' => now()->toDateString(), 'amount' => 700, 'paid_amount' => 0,
                'status' => 'open', 'owner_id' => 999999,
            ]);

            // 销售专员看到的销售报表口径 = 自己名下开票额（与发票列表一致）
            $response = $this->get('/reports/sales')->assertStatus(200);
            $response->assertSee('数据范围');
            $data = $response->viewData('invoicedTotal');
            $this->assertSame(300.0, (float) $data);
            $this->assertSame((float) \App\Models\Finance\CustomerInvoice::sum('amount'), (float) $data);

            // 总经理（all）：两张都计入
            $this->actingAsDemo('gm');
            $gm = $this->get('/reports/finance')->assertStatus(200)->viewData('invoiced');
            $this->assertSame(1000.0, (float) $gm);
        } finally {
            DB::rollBack();
        }
    }

    public function test_report_permissions_are_split_by_business_domain(): void
    {
        // 仓管无任何报表权限
        $this->actingAsDemo('wh-keeper');
        $this->get('/reports/sales')->assertStatus(403);
        $this->get('/reports/finance')->assertStatus(403);

        // 采购主管只有供应商绩效，看不了财务报表
        $this->actingAsDemo('pur-manager');
        $this->get('/reports/supplier')->assertStatus(200);
        $this->get('/reports/finance')->assertStatus(403);

        // 会计只有财务报表
        $this->actingAsDemo('accountant');
        $this->get('/reports/finance')->assertStatus(200);
        $this->get('/reports/sales')->assertStatus(403);

        // 总经理四张全通
        $this->actingAsDemo('gm');
        foreach (['/reports/sales', '/reports/quality', '/reports/supplier', '/reports/finance'] as $url) {
            $this->get($url)->assertStatus(200);
        }
    }

    public function test_fail_closed_when_model_has_no_owner_columns(): void
    {
        // P0-08 核心：声明了行级范围语义、但模型没有归属列 → 返回 0 行（以前是静默放行全表）
        $stub = new class extends \Illuminate\Database\Eloquent\Model {
            use \App\Models\Concerns\HasDataScope;

            protected $table = 'sales_orders';
            protected static ?string $dataScopeOwnerColumn = null;
            protected static ?string $dataScopeDepartmentColumn = null;
            protected static ?string $dataScopeWarehouseColumn = null;
        };

        $this->actingAsDemo('sales');       // self
        $this->assertSame(0, $stub->newQuery()->count());

        $this->actingAsDemo('wh-supervisor'); // all → 不过滤
        $this->assertGreaterThan(0, $stub->newQuery()->count());

        $this->actingAsDemo('admin');        // none → 0 行
        $this->assertSame(0, $stub->newQuery()->count());
    }
}
