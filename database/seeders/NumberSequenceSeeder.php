<?php

namespace Database\Seeders;

use App\Models\System\NumberSequence;
use Illuminate\Database\Seeder;

/**
 * 编号规则 Seeder：SO/PO/WO/GR/IN + 物料编码 各单据编号规则
 * 物料编码规则：material_code 前缀取分类码（2位），6位流水号
 */
class NumberSequenceSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // 物料编码：每种大类独立计数（rule_code = material_code:大类）
            ['rule_code' => 'material_code:EL', 'prefix' => 'EL', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：电子元器件'],
            ['rule_code' => 'material_code:ST', 'prefix' => 'ST', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：结构件'],
            ['rule_code' => 'material_code:PB', 'prefix' => 'PB', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：印制电路板'],
            ['rule_code' => 'material_code:PA', 'prefix' => 'PA', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：包材辅料'],
            ['rule_code' => 'material_code:WK', 'prefix' => 'WK', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：工装治具'],
            ['rule_code' => 'material_code:SM', 'prefix' => 'SM', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：半成品'],
            ['rule_code' => 'material_code:FG', 'prefix' => 'FG', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：成品'],
            ['rule_code' => 'material_code:SP', 'prefix' => 'SP', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：服务件/备件'],
            ['rule_code' => 'material_code:OP', 'prefix' => 'OP', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：运营物料'],
            ['rule_code' => 'material_code:CH', 'prefix' => 'CH', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => '物料内部编码：化学物料'],

            // 业务单据编号：前缀 + 日期 + 流水号
            ['rule_code' => 'sales_quote', 'prefix' => 'SQ', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '报价单编号'],
            ['rule_code' => 'sales_order', 'prefix' => 'SO', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '销售订单编号'],
            ['rule_code' => 'sales_return', 'prefix' => 'SR', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '退货单编号'],
            ['rule_code' => 'purchase_order', 'prefix' => 'PO', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '采购订单编号'],
            ['rule_code' => 'purchase_requisition', 'prefix' => 'PR', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '采购申请单编号'],
            ['rule_code' => 'work_order', 'prefix' => 'WO', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '生产工单编号'],
            ['rule_code' => 'goods_receipt', 'prefix' => 'GR', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '收货单编号'],
            ['rule_code' => 'shipment', 'prefix' => 'SH', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '出货单编号'],
            ['rule_code' => 'inventory_adjust', 'prefix' => 'IN', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '库存调整单编号'],
            ['rule_code' => 'bom_code', 'prefix' => 'BOM', 'date_format' => null, 'seq_length' => 6, 'current_seq' => 0, 'description' => 'BOM编号'],
            ['rule_code' => 'bom_import', 'prefix' => 'BI', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => 'BOM导入任务编号'],
            ['rule_code' => 'production_order', 'prefix' => 'WO', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '生产工单编号'],
            ['rule_code' => 'material_issue', 'prefix' => 'MI', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '领料单编号'],
            ['rule_code' => 'stocktake', 'prefix' => 'PD', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '盘点单编号'],
            ['rule_code' => 'rnd_project', 'prefix' => 'RD', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '研发项目编号'],
            ['rule_code' => 'inspection', 'prefix' => 'QC', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '检验记录编号'],
            ['rule_code' => 'crm_lead', 'prefix' => 'LD', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => 'CRM线索编号'],
            ['rule_code' => 'crm_op', 'prefix' => 'OP', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => 'CRM商机编号'],
            ['rule_code' => 'invoice', 'prefix' => 'INV', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '客户发票编号'],
            ['rule_code' => 'bill', 'prefix' => 'BILL', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '供应商账单编号'],
            ['rule_code' => 'payment', 'prefix' => 'PAY', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '收付款编号'],
            ['rule_code' => 'leave', 'prefix' => 'LV', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '请假单编号'],
            ['rule_code' => 'spu', 'prefix' => 'SPU', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '电商商品SPU编号'],
            ['rule_code' => 'coupon', 'prefix' => 'CP', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '优惠券编号'],
            ['rule_code' => 'ticket', 'prefix' => 'AS', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '售后工单编号'],
            ['rule_code' => 'voice', 'prefix' => 'VR', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '语音会议室编号'],
            ['rule_code' => 'reconcile', 'prefix' => 'RC', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '对账单编号'],
            ['rule_code' => 'fatx', 'prefix' => 'FT', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '财务流水编号'],
            ['rule_code' => 'req', 'prefix' => 'REQ', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '招聘需求编号'],
            ['rule_code' => 'plan', 'prefix' => 'PP', 'date_format' => 'Ymd', 'seq_length' => 4, 'current_seq' => 0, 'description' => '采购计划编号'],
        ];

        foreach ($rules as $rule) {
            NumberSequence::firstOrCreate(
                ['rule_code' => $rule['rule_code']],
                $rule
            );
        }
    }
}
