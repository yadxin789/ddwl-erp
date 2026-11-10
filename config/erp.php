<?php

return [

    /*
     * ERP 业务参数（D-16 等已拍板口径集中在此，避免散落在代码里）
     */
    'inventory' => [
        // 库存调整审批阈值（元，含等于）：|差异数量| × 单位成本 ≥ 该值需仓库主管审批
        // 低于阈值直接生效，但仍生成 inventory_adjustments 单据并标 auto_approved=true
        'adjust_approval_threshold' => (float) env('ERP_INVENTORY_ADJUST_THRESHOLD', 500),
    ],

    /*
     * 标准成本（D-14 / D-20 / §9.4）
     */
    'cost' => [
        // 没有 active 标准成本时是否硬性阻断出货（默认 false：演示/上线初期允许兜底，避免卡单）
        'block_ship_without_standard' => (bool) env('ERP_COST_BLOCK_SHIP', false),
        // 无 active 版本时是否按"最近采购价"临时生成 source=fallback 版本（页面标黄提示）
        'fallback_from_purchase_price' => (bool) env('ERP_COST_FALLBACK', true),
    ],

];
