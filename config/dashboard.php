<?php

/**
 * 岗位化工作台配置（v1.3 §4 / §6.12 / S4）
 *
 * 原则：工作台只显示"本岗要看的指标"，管理员不显示任何业务指标。
 * keys 与 config/navigation.php 的岗位键一致（role_*）。
 */

return [

    // 指标卡定义：key => 标题 / 图标 / 配色 / 是否金额 / 跳转路由（可选）
    'cards' => [
        'open_orders' => ['label' => '进行中销售订单', 'icon' => 'fa-cart-shopping', 'color' => 'primary', 'route' => 'sales.orders'],
        'open_pos' => ['label' => '进行中采购订单', 'icon' => 'fa-truck-ramp-box', 'color' => 'info', 'route' => 'purchase.orders'],
        'open_wos' => ['label' => '进行中生产工单', 'icon' => 'fa-gears', 'color' => 'warning', 'route' => 'production.orders'],
        'pending_qc' => ['label' => '待检记录', 'icon' => 'fa-clipboard-question', 'color' => 'danger', 'route' => 'quality.inspections'],
        'pending_issue_requests' => ['label' => '待我审批领料', 'icon' => 'fa-file-circle-plus', 'color' => 'warning', 'route' => 'production.issue-requests'],
        'ar_total' => ['label' => '应收未收', 'icon' => 'fa-hand-holding-dollar', 'color' => 'success', 'money' => true],
        'ap_total' => ['label' => '应付未付', 'icon' => 'fa-money-check-dollar', 'color' => 'danger', 'money' => true],
        'stock_qty' => ['label' => '库存总量', 'icon' => 'fa-warehouse', 'color' => 'secondary'],
        'materials' => ['label' => '物料总数', 'icon' => 'fa-boxes-stacked', 'color' => 'secondary'],
        'suppliers' => ['label' => '供应商数', 'icon' => 'fa-industry', 'color' => 'secondary'],
        'customers' => ['label' => '客户数', 'icon' => 'fa-users', 'color' => 'secondary'],
        'ecom_orders' => ['label' => '电商订单', 'icon' => 'fa-bag-shopping', 'color' => 'primary'],
        'my_projects' => ['label' => '我的项目', 'icon' => 'fa-diagram-project', 'color' => 'primary', 'route' => 'project.my'],
        'my_todos' => ['label' => '我的待办', 'icon' => 'fa-list-check', 'color' => 'info', 'route' => 'todos'],
        'sys_users' => ['label' => '账号数', 'icon' => 'fa-user-gear', 'color' => 'secondary'],
        'sys_roles' => ['label' => '角色数', 'icon' => 'fa-user-shield', 'color' => 'secondary'],
        // P1-02 标准成本口径（仅财务/总经理可见）
        'gross_profit_period' => ['label' => '本月毛利（标准成本）', 'icon' => 'fa-chart-line', 'color' => 'success', 'money' => true, 'route' => 'finance.cost-variance'],
        'price_variance_period' => ['label' => '本月采购价差', 'icon' => 'fa-scale-unbalanced', 'color' => 'warning', 'money' => true, 'route' => 'finance.cost-variance'],
    ],

    // 岗位 → 指标卡顺序（只列本岗需要的）
    'positions' => [
        // 系统管理员：只看运维规模，不看任何业务数字
        'role_sys_admin' => ['sys_users', 'sys_roles'],
        // 生产
        'role_prod_manager' => ['open_wos', 'pending_issue_requests', 'my_todos'],
        'role_prod_leader' => ['open_wos', 'my_todos'],
        'role_prod_operator' => ['my_todos'],
        // 品质
        'role_qc_manager' => ['pending_qc', 'my_todos'],
        'role_qc_iqc' => ['pending_qc', 'my_todos'],
        'role_qc_ipqc' => ['pending_qc', 'my_todos'],
        'role_qc_fqc' => ['pending_qc', 'my_todos'],
        // 采购
        'role_pur_manager' => ['open_pos', 'suppliers', 'ap_total', 'my_todos'],
        'role_pur_specialist' => ['open_pos', 'my_todos'],
        // 销售
        'role_sal_manager' => ['open_orders', 'ar_total', 'customers', 'my_todos'],
        'role_sal_specialist' => ['open_orders', 'customers', 'my_todos'],
        'role_sal_cs' => ['customers', 'my_todos'],
        // 研发
        'role_rnd_manager' => ['my_projects', 'my_todos'],
        'role_eng_hw' => ['my_projects', 'my_todos'],
        'role_eng_sw' => ['my_projects', 'my_todos'],
        'role_eng_me' => ['my_projects', 'my_todos'],
        'role_pkg_designer' => ['my_projects', 'my_todos'],
        'role_id_designer' => ['my_projects', 'my_todos'],
        // 仓储
        'role_wh_supervisor' => ['stock_qty', 'materials', 'my_todos'],
        'role_wh_keeper' => ['stock_qty', 'my_todos'],
        'role_wh_receiver' => ['my_todos'],
        // 行政 / 人事
        'role_adm_manager' => ['my_todos'],
        'role_hr_specialist' => ['my_todos'],
        // 财务（会计看账、出纳看钱）
        'role_fin_accountant' => ['ar_total', 'ap_total', 'gross_profit_period', 'price_variance_period', 'my_todos'],
        'role_fin_cashier' => ['ar_total', 'ap_total', 'my_todos'],
        // 电商
        'role_ecom_operator' => ['ecom_orders', 'my_todos'],
        'role_ecom_cs' => ['ecom_orders', 'my_todos'],
        // 总经理：只读驾驶舱口径
        'role_gm' => ['open_orders', 'open_pos', 'open_wos', 'ar_total', 'ap_total', 'gross_profit_period', 'price_variance_period', 'stock_qty'],
        // 兼容角色
        'role_project_creator' => ['my_projects', 'my_todos'],
        'role_task_executor' => ['my_projects', 'my_todos'],
        'role_task_auditor' => ['my_projects', 'my_todos'],
        'role_support_staff' => ['my_todos'],
    ],

    // 无岗位匹配 / 未配置时的兜底：只给待办，不暴露任何业务数字
    'default' => ['my_todos'],
];
