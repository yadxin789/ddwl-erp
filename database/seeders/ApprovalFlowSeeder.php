<?php

namespace Database\Seeders;

use App\Models\Approval\ApprovalFlow;
use App\Models\Organization\User;
use Illuminate\Database\Seeder;

/**
 * P1-01：6 条业务审批流（§9.2 审批链矩阵，D-15 / D-21）。
 *
 * steps_json 结构：
 * [
 *   ['step' => 1, 'name' => '采购主管主审', 'approver_id' => 12, 'delegate_ids' => [3], 'permission' => 'purchase.confirm'],
 *   ...
 * ]
 *
 * - approver_id：本步骤的**主审人**（具体用户）
 * - delegate_ids：可代批人（必须写理由，实例标 delegated=true，月度占比 >30% 告警）
 * - permission：该节点的岗位权限（仅作说明/守卫核对用，实际鉴权在引擎里按 id 判定）
 */
class ApprovalFlowSeeder extends Seeder
{
    public function run(): void
    {
        $uid = fn (string $username): int => (int) (User::where('username', $username)->value('id') ?? 0);

        foreach (['pur-manager', 'gm', 'wh-supervisor', 'accountant', 'rnd-manager', 'sal-manager'] as $u) {
            if ($uid($u) === 0) {
                $this->command?->warn("演示账号 {$u} 不存在，审批流的人选可能为 0（先跑 DemoAccountSeeder）");
            }
        }

        $flows = [
            [
                'flow_code' => 'flow_po_confirm',
                'flow_name' => '采购订单确认/关闭',
                'business_type' => 'purchase_order.confirm',
                'steps' => [
                    ['step' => 1, 'name' => '采购主管主审', 'approver_id' => $uid('pur-manager'),
                        'delegate_ids' => [$uid('gm')], 'permission' => 'purchase.confirm'],
                ],
            ],
            [
                'flow_code' => 'flow_stocktake_adjust',
                'flow_name' => '盘点差异调整（超阈值）',
                'business_type' => 'stocktake.adjust',
                'steps' => [
                    ['step' => 1, 'name' => '仓库主管单审', 'approver_id' => $uid('wh-supervisor'),
                        'delegate_ids' => [$uid('gm')], 'permission' => 'inventory.adjust.approve'],
                ],
            ],
            [
                'flow_code' => 'flow_payment',
                'flow_name' => '付款执行（会计 → 总经理）',
                'business_type' => 'payment.execute',
                'steps' => [
                    ['step' => 1, 'name' => '会计审核', 'approver_id' => $uid('accountant'),
                        'delegate_ids' => [], 'permission' => 'finance.payment.approve'],
                    ['step' => 2, 'name' => '总经理审批', 'approver_id' => $uid('gm'),
                        'delegate_ids' => [], 'permission' => 'finance.payment.approve'],
                ],
            ],
            [
                'flow_code' => 'flow_invoice',
                'flow_name' => '发票开具（会计 → 总经理）',
                'business_type' => 'invoice.issue',
                'steps' => [
                    ['step' => 1, 'name' => '会计审核', 'approver_id' => $uid('accountant'),
                        'delegate_ids' => [], 'permission' => 'finance.invoice.approve'],
                    ['step' => 2, 'name' => '总经理审批', 'approver_id' => $uid('gm'),
                        'delegate_ids' => [], 'permission' => 'finance.invoice.approve'],
                ],
            ],
            [
                'flow_code' => 'flow_rnd_extension',
                'flow_name' => '研发任务延期（研发经理单审）',
                'business_type' => 'rnd.extension',
                'steps' => [
                    ['step' => 1, 'name' => '研发经理审批', 'approver_id' => $uid('rnd-manager'),
                        'delegate_ids' => [], 'permission' => 'rnd.extension.approve'],
                ],
            ],
            [
                'flow_code' => 'flow_ecr_change',
                'flow_name' => '研发变更单 ECR（研发经理单审）',
                'business_type' => 'ecr.change',
                'steps' => [
                    ['step' => 1, 'name' => '研发经理审批', 'approver_id' => $uid('rnd-manager'),
                        'delegate_ids' => [], 'permission' => 'rnd.approve'],
                ],
            ],
            [
                'flow_code' => 'flow_sales_return',
                'flow_name' => '客户退货（销售主管单审）',
                'business_type' => 'sales_return.approve',
                'steps' => [
                    ['step' => 1, 'name' => '销售主管审批', 'approver_id' => $uid('sal-manager'),
                        'delegate_ids' => [], 'permission' => 'sales.return.approve'],
                ],
            ],
        ];

        foreach ($flows as $f) {
            ApprovalFlow::updateOrCreate(
                ['flow_code' => $f['flow_code']],
                [
                    'flow_name' => $f['flow_name'],
                    'business_type' => $f['business_type'],
                    'steps_json' => $f['steps'],
                    'status' => true,
                ]
            );
        }

        $this->command?->info('审批流已写入 ' . count($flows) . ' 条（purchase/stocktake/payment/invoice/rnd/ecr/return）。');
    }
}
