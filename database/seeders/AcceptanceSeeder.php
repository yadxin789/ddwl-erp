<?php

namespace Database\Seeders;

use App\Enums\StockStatus;
use App\Models\Bom\BomHeader;
use App\Models\Bom\BomLine;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\InventoryTransaction;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use App\Models\MasterData\Customer;
use App\Models\MasterData\Material;
use App\Models\MasterData\MaterialCategory;
use App\Models\MasterData\Supplier;
use App\Models\Organization\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * P1-04 端到端验收剧本种子（可重复跑：先按固定编码清理，再重建）。
 *
 * 内容：1 客户 / 2 供应商 / 5 物料（2 成品 + 2 原料 + 1 包材）/ 2 BOM /
 *       1 销售单（FG01 ×10 @50）/ 1 采购单（RM01 200@2、RM02 100@1）/ 1 生产工单（FG01 ×10）。
 * 固定编码：ACC-C01、ACC-S01/02、ACC-RM01/02、ACC-PK01、ACC-FG01/02、ACC-BOM1/2、SO-ACC-001、PO-ACC-001、WO-ACC-001。
 */
class AcceptanceSeeder extends Seeder
{
    public const CUSTOMER_CODE = 'ACC-C01';
    public const SUPPLIER_A = 'ACC-S01';
    public const SUPPLIER_B = 'ACC-S02';
    public const SALES_ORDER_NO = 'SO-ACC-001';
    public const PURCHASE_ORDER_NO = 'PO-ACC-001';
    public const PRODUCTION_ORDER_NO = 'WO-ACC-001';
    public const BOM_1 = 'ACC-BOM1';
    public const BOM_2 = 'ACC-BOM2';

    public function run(): void
    {
        $this->cleanup();

        $warehouse = Warehouse::where('warehouse_code', 'WH-RM')->first();
        if (! $warehouse) {
            $this->command?->warn('验收种子跳过：缺少 WH-RM 仓库（请先跑 WarehouseSeeder）。');

            return;
        }
        $location = WarehouseLocation::where('warehouse_id', $warehouse->id)->where('zone', '合格区')->first();
        $locationId = $location?->id ?? 1;

        $adminId = (int) (User::where('username', '张三')->value('id') ?? 0);
        $purchaseUser = User::where('username', '赵六')->first();
        $salesUser = User::where('username', '钱七')->first();
        $prodUser = User::where('username', '吴十')->first();
        $purchaseId = (int) ($purchaseUser?->id ?? $adminId);
        $salesId = (int) ($salesUser?->id ?? $adminId);
        $prodId = (int) ($prodUser?->id ?? $adminId);
        $salesDept = (int) ($salesUser?->department_id ?? 0);
        $purchaseDept = (int) ($purchaseUser?->department_id ?? 0);
        $prodDept = (int) ($prodUser?->department_id ?? 0);

        // 分类字典：确保 FG / EL / PA 存在
        foreach ([
            ['category_code' => 'FG', 'category_name' => '成品'],
            ['category_code' => 'EL', 'category_name' => '电子元器件'],
            ['category_code' => 'PA', 'category_name' => '包材辅料'],
        ] as $cat) {
            MaterialCategory::firstOrCreate(['category_code' => $cat['category_code']], $cat + ['status' => 1]);
        }

        $customer = Customer::firstOrCreate(['customer_code' => self::CUSTOMER_CODE], [
            'customer_name' => '验收客户（演示）',
            'contact_person' => '验收联系人',
            'contact_phone' => '13800009999',
            'payment_terms' => '月结30天',
            'credit_limit' => 500000,
            'status' => 1,
            'owner_id' => $salesId,
            'department_id' => $salesDept,
        ]);

        $supplierA = Supplier::firstOrCreate(['supplier_code' => self::SUPPLIER_A], [
            'supplier_name' => '验收供应商A（演示）',
            'contact_person' => 'A 联系人',
            'contact_phone' => '13900009901',
            'supplier_type' => '原厂',
            'lead_time_days' => 7,
            'status' => 1,
            'owner_id' => $purchaseId,
            'department_id' => $purchaseDept,
        ]);
        $supplierB = Supplier::firstOrCreate(['supplier_code' => self::SUPPLIER_B], [
            'supplier_name' => '验收供应商B（演示）',
            'contact_person' => 'B 联系人',
            'contact_phone' => '13900009902',
            'supplier_type' => '代理',
            'lead_time_days' => 3,
            'status' => 1,
            'owner_id' => $purchaseId,
            'department_id' => $purchaseDept,
        ]);

        $materials = [
            ['ACC-RM01', 'EL', '验收原料电阻', '0402 10KΩ', 'PCS', 2.0000],
            ['ACC-RM02', 'EL', '验收原料电容', '0402 100nF', 'PCS', 1.0000],
            ['ACC-PK01', 'PA', '验收包装盒', '100×60×40mm', 'PCS', 0.5000],
            ['ACC-FG01', 'FG', '验收成品一', 'ACC-FG01 成品', 'PCS', 30.0000],
            ['ACC-FG02', 'FG', '验收成品二', 'ACC-FG02 成品', 'PCS', 30.0000],
        ];
        $map = [];
        foreach ($materials as [$code, $category, $name, $spec, $unit, $cost]) {
            $map[$code] = Material::firstOrCreate(['material_code' => $code], [
                'category_code' => $category,
                'name' => $name,
                'spec' => $spec,
                'unit' => $unit,
                'cost_price' => $cost,
                'status' => 1,
                'remark' => 'P1-04 验收剧本数据',
            ]);
        }

        // 2 个 BOM
        $bom1 = BomHeader::create([
            'bom_code' => self::BOM_1,
            'product_material_id' => $map['ACC-FG01']->id,
            'version' => 'V1',
            'status' => 'active',
            'effective_date' => now()->toDateString(),
            'remark' => 'P1-04 验收 BOM：FG01 = 2×RM01 + 1×RM02',
            'created_by' => $adminId,
            'approved_by' => $adminId,
            'approved_at' => now(),
        ]);
        BomLine::create(['bom_header_id' => $bom1->id, 'parent_material_id' => $map['ACC-FG01']->id, 'material_id' => $map['ACC-RM01']->id, 'qty_per' => 2, 'unit' => 'PCS', 'loss_rate' => 0, 'sort_order' => 1]);
        BomLine::create(['bom_header_id' => $bom1->id, 'parent_material_id' => $map['ACC-FG01']->id, 'material_id' => $map['ACC-RM02']->id, 'qty_per' => 1, 'unit' => 'PCS', 'loss_rate' => 0, 'sort_order' => 2]);

        $bom2 = BomHeader::create([
            'bom_code' => self::BOM_2,
            'product_material_id' => $map['ACC-FG02']->id,
            'version' => 'V1',
            'status' => 'active',
            'effective_date' => now()->toDateString(),
            'remark' => 'P1-04 验收 BOM：FG02 = 1×RM01 + 1×PK01',
            'created_by' => $adminId,
            'approved_by' => $adminId,
            'approved_at' => now(),
        ]);
        BomLine::create(['bom_header_id' => $bom2->id, 'parent_material_id' => $map['ACC-FG02']->id, 'material_id' => $map['ACC-RM01']->id, 'qty_per' => 1, 'unit' => 'PCS', 'loss_rate' => 0, 'sort_order' => 1]);
        BomLine::create(['bom_header_id' => $bom2->id, 'parent_material_id' => $map['ACC-FG02']->id, 'material_id' => $map['ACC-PK01']->id, 'qty_per' => 1, 'unit' => 'PCS', 'loss_rate' => 0, 'sort_order' => 2]);

        // 原料初始库存（批次空串：生产领料按 lot_no='' 取料）
        foreach (['ACC-RM01' => 100, 'ACC-RM02' => 100, 'ACC-PK01' => 100] as $code => $qty) {
            InventoryStock::create([
                'material_id' => $map[$code]->id,
                'lot_no' => '',
                'warehouse_id' => $warehouse->id,
                'location_id' => 0, // 生产领料/完工入库按 location_id=0 取库存行（与 ProductionOrderService 一致）
                'status' => StockStatus::Available->value,
                'qty' => $qty,
                'reserved_qty' => 0,
                'receive_date' => now()->toDateString(),
            ]);
            InventoryTransaction::create([
                'material_id' => $map[$code]->id,
                'lot_no' => '',
                'warehouse_id' => $warehouse->id,
                'location_id' => 0,
                'txn_type' => 'receipt',
                'qty_change' => $qty,
                'qty_before' => 0,
                'qty_after' => $qty,
                'to_status' => StockStatus::Available->value,
                'ref_type' => 'acceptance',
                'ref_id' => null,
                'operator_id' => $adminId,
                'remark' => 'P1-04 验收剧本初始库存',
                'created_at' => now(),
            ]);
        }

        // 1 销售单（草稿，测试里走确认→出货）
        $soId = (int) DB::table('sales_orders')->insertGetId([
            'order_no' => self::SALES_ORDER_NO,
            'customer_id' => $customer->id,
            'order_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(7)->toDateString(),
            'status' => 'draft',
            'total_amount' => 500,
            'shipped_qty' => 0,
            'salesperson_id' => $salesId,
            'remark' => 'P1-04 验收剧本',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sales_order_lines')->insert([
            'order_id' => $soId,
            'material_id' => $map['ACC-FG01']->id,
            'qty' => 10,
            'shipped_qty' => 0,
            'unit_price' => 50,
        ]);

        // 1 采购单（草稿，测试里走确认→收货→质检）
        $poId = (int) DB::table('purchase_orders')->insertGetId([
            'po_no' => self::PURCHASE_ORDER_NO,
            'supplier_id' => $supplierA->id,
            'buyer_id' => $purchaseId,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'status' => 'draft',
            'total_amount' => 500,
            'received_qty' => 0,
            'remark' => 'P1-04 验收剧本',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('purchase_order_lines')->insert([
            ['po_id' => $poId, 'material_id' => $map['ACC-RM01']->id, 'qty' => 200, 'received_qty' => 0, 'unit_price' => 2],
            ['po_id' => $poId, 'material_id' => $map['ACC-RM02']->id, 'qty' => 100, 'received_qty' => 0, 'unit_price' => 1],
        ]);

        // 1 生产工单（计划 10 台 FG01）
        DB::table('production_orders')->insert([
            'order_no' => self::PRODUCTION_ORDER_NO,
            'product_material_id' => $map['ACC-FG01']->id,
            'bom_id' => $bom1->id,
            'routing_id' => null,
            'plan_qty' => 10,
            'completed_qty' => 0,
            'defective_qty' => 0,
            'status' => 'planned',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'sales_order_id' => $soId,
            'department_id' => $prodDept,
            'created_by' => $prodId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info('P1-04 验收数据已重建：1 客户 / 2 供应商 / 5 物料 / 2 BOM / 1 销售单 / 1 采购单 / 1 工单。');
    }

    /**
     * 按固定编码清理上一轮验收数据（可重复跑；也供 erp:acceptance:clean 使用）。
     */
    public function cleanup(): void
    {
        $codes = ['ACC-RM01', 'ACC-RM02', 'ACC-PK01', 'ACC-FG01', 'ACC-FG02'];
        $materialIds = Material::whereIn('material_code', $codes)->pluck('id')->all();

        DB::table('production_orders')->where('order_no', self::PRODUCTION_ORDER_NO)->delete();
        DB::table('sales_order_lines')->whereIn('order_id', DB::table('sales_orders')->where('order_no', self::SALES_ORDER_NO)->pluck('id'))->delete();
        DB::table('sales_orders')->where('order_no', self::SALES_ORDER_NO)->delete();
        DB::table('purchase_order_lines')->whereIn('po_id', DB::table('purchase_orders')->where('po_no', self::PURCHASE_ORDER_NO)->pluck('id'))->delete();
        DB::table('purchase_orders')->where('po_no', self::PURCHASE_ORDER_NO)->delete();
        DB::table('bom_lines')->whereIn('bom_header_id', BomHeader::whereIn('bom_code', [self::BOM_1, self::BOM_2])->pluck('id'))->delete();
        BomHeader::whereIn('bom_code', [self::BOM_1, self::BOM_2])->delete();

        // 验收物料的库存/流水/挂账凭证
        foreach ($materialIds as $id) {
            InventoryStock::where('material_id', $id)->delete();
            InventoryTransaction::where('material_id', $id)->delete();
        }

        Customer::where('customer_code', self::CUSTOMER_CODE)->delete();
        Supplier::whereIn('supplier_code', [self::SUPPLIER_A, self::SUPPLIER_B])->delete();
        Material::whereIn('material_code', $codes)->delete();
    }
}
