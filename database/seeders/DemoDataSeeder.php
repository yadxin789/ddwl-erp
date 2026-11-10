<?php

namespace Database\Seeders;

use App\Enums\StockStatus;
use App\Models\MasterData\Customer;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\InventoryTransaction;
use App\Models\MasterData\Material;
use App\Models\MasterData\MaterialCategory;
use App\Models\MasterData\MaterialMpn;
use App\Models\MasterData\MaterialSubcategory;
use App\Models\MasterData\Supplier;
use App\Models\System\Unit;
use App\Models\Organization\User;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 演示数据 Seeder：分类字典（10大类+46中类）+ 计量单位 + 物料 + MPN + 客户 + 供应商 + 初始库存
 * 保证各业务演示流程可以完整走通
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUnits();
        $this->seedMaterialCategories();
        $this->seedMaterials();
        $this->seedCustomers();
        $this->seedSuppliers();
        $this->seedInitialStock();
    }

    /**
     * 计量单位
     */
    private function seedUnits(): void
    {
        $units = [
            ['unit_code' => 'PCS', 'unit_name' => '个', 'conversion_factor' => 1],
            ['unit_code' => 'K', 'unit_name' => '千个', 'conversion_factor' => 1000],
            ['unit_code' => 'M', 'unit_name' => '百万个', 'conversion_factor' => 1000000],
            ['unit_code' => 'ROLL', 'unit_name' => '卷', 'conversion_factor' => null],
            ['unit_code' => 'REEL', 'unit_name' => '盘', 'conversion_factor' => null],
            ['unit_code' => 'SET', 'unit_name' => '套', 'conversion_factor' => null],
            ['unit_code' => 'BOX', 'unit_name' => '盒', 'conversion_factor' => null],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['unit_code' => $unit['unit_code']], $unit);
        }
    }

    /**
     * 物料分类字典：10 大类 + 46 中类（对齐 物料分类字典.csv）
     */
    private function seedMaterialCategories(): void
    {
        $categories = [
            'EL' => ['category_name' => '电子元器件', 'description' => '电子元器件', 'sort_order' => 1],
            'ST' => ['category_name' => '结构件', 'description' => '结构件', 'sort_order' => 2],
            'PB' => ['category_name' => '印制电路板', 'description' => '印制电路板', 'sort_order' => 3],
            'PA' => ['category_name' => '包材辅料', 'description' => '包材辅料', 'sort_order' => 4],
            'WK' => ['category_name' => '工装治具', 'description' => '工装治具', 'sort_order' => 5],
            'SM' => ['category_name' => '半成品', 'description' => '半成品', 'sort_order' => 6],
            'FG' => ['category_name' => '成品', 'description' => '成品', 'sort_order' => 7],
            'SP' => ['category_name' => '服务件/备件', 'description' => '服务件/备件', 'sort_order' => 8],
            'OP' => ['category_name' => '运营物料', 'description' => '运营物料', 'sort_order' => 9],
            'CH' => ['category_name' => '化学物料', 'description' => '化学物料', 'sort_order' => 10],
        ];

        $subcategories = [
            'EL' => [
                'EL-R' => '电阻器', 'EL-C' => '电容器', 'EL-L' => '电感器', 'EL-D' => '二极管',
                'EL-Q' => '三极管/MOSFET', 'EL-U' => '集成电路IC', 'EL-Y' => '晶振振荡器',
                'EL-F' => '保险丝保护器件', 'EL-K' => '开关按键', 'EL-S' => '连接器插座',
                'EL-T' => '变压器线圈', 'EL-J' => '继电器', 'EL-M' => '电机马达',
                'EL-P' => '显示器件', 'EL-B' => '电池电源模块',
            ],
            'ST' => [
                'ST-CS' => '外壳/壳体', 'ST-SC' => '螺丝紧固件', 'ST-PC' => '铜柱/螺柱',
                'ST-HT' => '散热片', 'ST-MT' => '钣金件', 'ST-DC' => '压铸件',
                'ST-RB' => '橡胶件', 'ST-LB' => '线缆线束',
            ],
            'PB' => [
                'PB-PCB' => '裸板PCB', 'PB-FPC' => '柔性电路板',
            ],
            'PA' => [
                'PA-BOX' => '纸箱/外箱', 'PA-FM' => '泡棉/珍珠棉', 'PA-TAPE' => '胶带/标签',
                'PA-ESD' => '防静电袋/泡', 'PA-BAG' => '普通包装袋', 'PA-ACC' => '辅料包',
            ],
            'WK' => [
                'WK-TF' => '测试治具', 'WK-BN' => '烧录治具', 'WK-ST' => '钢网', 'WK-FX' => '夹具/载具',
            ],
            'SM' => [
                'SM-PCBA' => 'PCBA模块', 'SM-ASM' => '组装半成品',
            ],
            'FG' => [
                'FG-FG' => '整机成品', 'FG-KIT' => '套件成品',
            ],
            'SP' => [
                'SP-SP' => '售后备件',
            ],
            'OP' => [
                'OP-CL' => '清洁耗材', 'OP-GL' => '手套/指套', 'OP-OF' => '办公耗材',
            ],
            'CH' => [
                'CH-SP' => '锡膏/红胶', 'CH-CF' => '清洗剂', 'CH-CO' => '三防漆', 'CH-OT' => '其他化学品',
            ],
        ];

        foreach ($categories as $code => $data) {
            MaterialCategory::firstOrCreate(
                ['category_code' => $code],
                [
                    'category_name' => $data['category_name'],
                    'description' => $data['description'],
                    'sort_order' => $data['sort_order'],
                    'status' => 1,
                ]
            );

            foreach ($subcategories[$code] as $subCode => $subName) {
                MaterialSubcategory::firstOrCreate(
                    ['subcategory_code' => $subCode],
                    [
                        'category_code' => $code,
                        'subcategory_name' => $subName,
                        'description' => $subName,
                    ]
                );
            }
        }
    }

    /**
     * 演示物料：电子元器件（含 MPN）+ 半成品 + 成品
     */
    private function seedMaterials(): void
    {
        $materials = [
            // 电子元器件
            ['material_code' => 'EL000001', 'category_code' => 'EL', 'subcategory_code' => 'EL-R', 'name' => '贴片电阻', 'spec' => '10KΩ ±1% 0402 1/16W', 'package' => '0402', 'unit' => 'K', 'cost_price' => 0.0035],
            ['material_code' => 'EL000002', 'category_code' => 'EL', 'subcategory_code' => 'EL-C', 'name' => '陶瓷电容', 'spec' => '100nF ±10% 50V 0402 X7R', 'package' => '0402', 'unit' => 'K', 'cost_price' => 0.004],
            ['material_code' => 'EL000003', 'category_code' => 'EL', 'subcategory_code' => 'EL-U', 'name' => 'MCU微控制器', 'spec' => 'STM32F103C8T6 LQFP-48', 'package' => 'LQFP-48', 'unit' => 'PCS', 'cost_price' => 8.5],
            ['material_code' => 'EL000004', 'category_code' => 'EL', 'subcategory_code' => 'EL-S', 'name' => 'USB连接器', 'spec' => 'TYPE-C 16P 贴片母座', 'package' => 'SMD', 'unit' => 'PCS', 'cost_price' => 0.35],
            ['material_code' => 'EL000005', 'category_code' => 'EL', 'subcategory_code' => 'EL-Y', 'name' => '无源晶振', 'spec' => '8MHz ±10ppm 3225', 'package' => '3225', 'unit' => 'PCS', 'cost_price' => 0.12],
            // 结构件
            ['material_code' => 'ST000001', 'category_code' => 'ST', 'subcategory_code' => 'ST-CS', 'name' => '塑胶外壳', 'spec' => 'ABS 白色 上下盖套件', 'package' => null, 'unit' => 'SET', 'cost_price' => 3.2],
            ['material_code' => 'ST000002', 'category_code' => 'ST', 'subcategory_code' => 'ST-SC', 'name' => '自攻螺丝', 'spec' => 'M2x4 十字盘头', 'package' => null, 'unit' => 'K', 'cost_price' => 0.015],
            // 印制电路板
            ['material_code' => 'PB000001', 'category_code' => 'PB', 'subcategory_code' => 'PB-PCB', 'name' => '主控板PCB', 'spec' => 'FR-4 四层板 1.6mm 沉金', 'package' => null, 'unit' => 'PCS', 'cost_price' => 4.8],
            // 半成品
            ['material_code' => 'SM000001', 'category_code' => 'SM', 'subcategory_code' => 'SM-PCBA', 'name' => '主控PCBA', 'spec' => 'STM32F103 主控板贴片完成', 'package' => null, 'unit' => 'PCS', 'cost_price' => 22.0],
            // 成品
            ['material_code' => 'FG000001', 'category_code' => 'FG', 'subcategory_code' => 'FG-FG', 'name' => '智能时钟成品', 'spec' => '电子时钟 温度显示 USB供电', 'package' => null, 'unit' => 'PCS', 'cost_price' => 35.0],
        ];

        foreach ($materials as $mat) {
            Material::firstOrCreate(
                ['material_code' => $mat['material_code']],
                $mat + ['rohs_status' => 1, 'status' => 1]
            );
        }

        // MPN 关联（电阻/电容/MCU）
        $mpns = [
            ['material_code' => 'EL000001', 'mpn' => 'RC0402FR-0710KL', 'manufacturer' => 'Yageo', 'is_primary' => 1],
            ['material_code' => 'EL000001', 'mpn' => '0402WGF1002TCE', 'manufacturer' => 'Uniroyal', 'is_primary' => 0],
            ['material_code' => 'EL000002', 'mpn' => 'GRM155R71H104KA88D', 'manufacturer' => 'Murata', 'is_primary' => 1],
            ['material_code' => 'EL000003', 'mpn' => 'STM32F103C8T6', 'manufacturer' => 'ST', 'is_primary' => 1],
            ['material_code' => 'EL000004', 'mpn' => 'TYPE-C-16P-SMD', 'manufacturer' => 'Jing Extension', 'is_primary' => 1],
        ];

        foreach ($mpns as $mpn) {
            $material = Material::where('material_code', $mpn['material_code'])->first();
            if (! $material) {
                continue;
            }

            MaterialMpn::firstOrCreate(
                ['mpn' => $mpn['mpn'], 'manufacturer' => $mpn['manufacturer']],
                [
                    'material_id' => $material->id,
                    'is_primary' => $mpn['is_primary'],
                    'is_alternative' => 0,
                    'approved_by' => 'admin',
                    'approved_at' => now(),
                ]
            );
        }
    }

    /**
     * 演示客户
     */
    private function seedCustomers(): void
    {
        $customers = [
            ['customer_code' => 'C0001', 'customer_name' => '深圳华信电子有限公司', 'contact_person' => '张经理', 'contact_phone' => '13800000001', 'payment_terms' => '月结30天', 'credit_limit' => 500000],
            ['customer_code' => 'C0002', 'customer_name' => '东莞创科科技有限公司', 'contact_person' => '李工', 'contact_phone' => '13800000002', 'payment_terms' => '月结60天', 'credit_limit' => 300000],
            ['customer_code' => 'C0003', 'customer_name' => '广州明远贸易有限公司', 'contact_person' => '王总', 'contact_phone' => '13800000003', 'payment_terms' => '现款现货', 'credit_limit' => 100000],
        ];

        foreach ($customers as $customer) {
            Customer::firstOrCreate(
                ['customer_code' => $customer['customer_code']],
                $customer + ['status' => 1]
            );
        }
    }

    /**
     * 演示供应商
     */
    private function seedSuppliers(): void
    {
        $suppliers = [
            ['supplier_code' => 'S0001', 'supplier_name' => '国巨电子（中国）有限公司', 'contact_person' => '陈经理', 'contact_phone' => '13900000001', 'supplier_type' => '原厂', 'lead_time_days' => 7],
            ['supplier_code' => 'S0002', 'supplier_name' => '村田电子贸易有限公司', 'contact_person' => '刘工', 'contact_phone' => '13900000002', 'supplier_type' => '原厂', 'lead_time_days' => 10],
            ['supplier_code' => 'S0003', 'supplier_name' => '深圳世强先进科技有限公司', 'contact_person' => '赵经理', 'contact_phone' => '13900000003', 'supplier_type' => '代理', 'lead_time_days' => 3],
            ['supplier_code' => 'S0004', 'supplier_name' => '深圳华强PCB板厂', 'contact_person' => '周工', 'contact_phone' => '13900000004', 'supplier_type' => '加工', 'lead_time_days' => 5],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(
                ['supplier_code' => $supplier['supplier_code']],
                $supplier + ['status' => 1]
            );
        }
    }

    /**
     * 初始库存：原料仓合格区库存 + 初始入库流水
     */
    private function seedInitialStock(): void
    {
        $admin = User::where('username', 'admin')->first();
        $warehouse = Warehouse::where('warehouse_code', 'WH-RM')->first();
        if (! $warehouse) {
            return;
        }
        $okLocation = WarehouseLocation::where('warehouse_id', $warehouse->id)->where('zone', '合格区')->first();

        $stocks = [
            ['material_code' => 'EL000001', 'lot_no' => 'LOT20261101', 'qty' => 5000],
            ['material_code' => 'EL000002', 'lot_no' => 'LOT20261101', 'qty' => 5000],
            ['material_code' => 'EL000003', 'lot_no' => 'LOT20261101', 'qty' => 200],
            ['material_code' => 'EL000004', 'lot_no' => 'LOT20261101', 'qty' => 1000],
            ['material_code' => 'EL000005', 'lot_no' => 'LOT20261101', 'qty' => 1000],
            ['material_code' => 'ST000001', 'lot_no' => 'LOT20261101', 'qty' => 500],
            ['material_code' => 'ST000002', 'lot_no' => 'LOT20261101', 'qty' => 10000],
            ['material_code' => 'PB000001', 'lot_no' => 'LOT20261101', 'qty' => 800],
        ];

        foreach ($stocks as $stock) {
            $material = Material::where('material_code', $stock['material_code'])->first();
            if (! $material) {
                continue;
            }

            $stockRow = InventoryStock::firstOrCreate(
                [
                    'material_id' => $material->id,
                    'lot_no' => $stock['lot_no'],
                    'warehouse_id' => $warehouse->id,
                    'location_id' => $okLocation?->id ?? 1,
                    'status' => StockStatus::Available->value,
                ],
                [
                    'qty' => $stock['qty'],
                    'reserved_qty' => 0,
                    'receive_date' => now()->toDateString(),
                ]
            );

            // 初始入库流水
            InventoryTransaction::create([
                'material_id' => $material->id,
                'lot_no' => $stock['lot_no'],
                'warehouse_id' => $warehouse->id,
                'location_id' => $okLocation?->id ?? 1,
                'txn_type' => 'receipt',
                'qty_change' => $stock['qty'],
                'qty_before' => 0,
                'qty_after' => $stock['qty'],
                'from_status' => null,
                'to_status' => StockStatus::Available->value,
                'ref_type' => 'initial',
                'ref_id' => null,
                'operator_id' => $admin?->id,
                'remark' => '初始库存导入',
                'created_at' => now(),
            ]);
        }
    }
}
