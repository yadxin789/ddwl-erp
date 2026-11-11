<?php
/**
 * 岗位侧边栏菜单配置（唯一菜单事实源）
 *
 * 生成自《岗位-菜单-页面规划-v1.3》§5.3 / §5.4 / §5.6，请勿手改结构；
 * 调整菜单请同时更新文档与 §5.5 数量核对表。
 *
 * 约束：
 *  - 每个岗位 roles 至少 1 个；menu 必须包含 shared 全部 11 项；
 *  - menu_items 是"名称 → 路由 → 权限"的唯一映射，侧边栏按名称渲染；
 *  - 路由不存在时侧边栏跳过该名称（不渲染死链接），由 erp:guard:check 报告。
 */

return [


    'shared' => [
        '工作台', '我的待办', '审批中心', '消息通知', '聊天室', '知识库',
        'AI 助手', '个人资料', '资料文件', '企业微信绑定', '我的项目列表',
    ],

    'positions' => [

        // ── 系统（非业务） ──
        'role_sys_admin' => [
            'code' => 'SYS-ADMIN', 'name' => '系统管理员', 'department' => '系统（非业务）',
            'roles' => ['role_sys_admin'],
            'menu' => [
                '工作台',
                '用户管理', '部门管理', '岗位管理', '角色管理', '权限管理', '基础数据管理',
                'AI 开通与设置', '企业微信设置',
                '系统运维', '系统可观测', '同步任务', '接口日志', '状态映射', '事件队列', '多租户', '安全审计',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 生产部 ──
        'role_prod_manager' => [
            'code' => 'D-PROD-P01', 'name' => '生产主管', 'department' => '生产部',
            'roles' => ['role_prod_manager'],
            'menu' => [
                '工作台',
                '生产工单', '领料单', '库存盘点', '工作中心', '工艺路线', '产能排程', '检验记录', '采购申请', '物料查询',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_prod_leader' => [
            'code' => 'D-PROD-P02', 'name' => '生产组长', 'department' => '生产部',
            'roles' => ['role_prod_leader'],
            'menu' => [
                '工作台',
                '生产工单', '领料单', '领料申请', '库存盘点', '物料查询', '库存查询',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_prod_operator' => [
            'code' => 'D-PROD-P03', 'name' => '作业员', 'department' => '生产部',
            'roles' => ['role_prod_operator'],
            'menu' => [
                '工作台', '我的工单', '我的报工',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 品质部 ──
        'role_qc_manager' => [
            'code' => 'D-QC-P01', 'name' => '品质主管', 'department' => '品质部',
            'roles' => ['role_qc_manager'],
            'menu' => [
                '工作台',
                '检验记录', '检验模板维护', '缺陷代码维护', '收货检验', '供应商绩效', '质量报表', '物料查询',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_qc_iqc' => [
            'code' => 'D-QC-P02', 'name' => 'IQC检验员', 'department' => '品质部',
            'roles' => ['role_qc_iqc'],
            'menu' => [
                '工作台', '待检任务', '检验录入', '来料收货', '检验模板', '缺陷代码',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_qc_ipqc' => [
            'code' => 'D-QC-P03', 'name' => 'IPQC检验员', 'department' => '品质部',
            'roles' => ['role_qc_ipqc'],
            'menu' => [
                '工作台', '待检任务', '检验录入', '在制工单', '检验模板', '缺陷代码',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_qc_fqc' => [
            'code' => 'D-QC-P04', 'name' => 'FQC检验员', 'department' => '品质部',
            'roles' => ['role_qc_fqc'],
            'menu' => [
                '工作台', '待检任务', '检验录入', '待出货订单', '检验模板', '缺陷代码',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 采购部 ──
        'role_pur_manager' => [
            'code' => 'D-PUR-P01', 'name' => '采购主管', 'department' => '采购部',
            'roles' => ['role_pur_manager'],
            'menu' => [
                '工作台',
                '采购申请', '采购订单', '收货进度', '供应商档案', '供应商绩效', '物料查询', '库存查询',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_pur_specialist' => [
            'code' => 'D-PUR-P02', 'name' => '采购专员', 'department' => '采购部',
            'roles' => ['role_pur_specialist'],
            'menu' => [
                '工作台',
                '采购申请', '采购订单', '收货进度', '采购计划', 'MRP 采购建议', '供应商档案', '物料查询', '库存查询',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 销售部 ──
        'role_sal_manager' => [
            'code' => 'D-SAL-P01', 'name' => '销售主管', 'department' => '销售部',
            'roles' => ['role_sal_manager'],
            'menu' => [
                '工作台', '销售订单', '报价单', '客户退货', '客户档案', '销售分析',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_sal_specialist' => [
            'code' => 'D-SAL-P02', 'name' => '销售专员', 'department' => '销售部',
            'roles' => ['role_sal_specialist'],
            'menu' => [
                '工作台',
                '报价单', '销售订单', '客户退货', '客户档案', '销售线索', '销售商机', '跟进记录', '库存查询',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_sal_cs' => [
            'code' => 'D-SAL-P03', 'name' => '客服', 'department' => '销售部',
            'roles' => ['role_sal_cs'],
            'menu' => [
                '工作台', '售后工单', '客户退货', '跟进记录', '销售订单', '客户档案',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 研发部（5 岗，均带前缀，不合并） ──
        'role_rnd_manager' => [
            'code' => 'D-RND-P01', 'name' => '研发经理', 'department' => '研发部',
            'roles' => ['role_rnd_manager'],
            'menu' => [
                '工作台',
                '项目模板', '研发项目', '交付物字典', '文档审核', 'BOM管理', 'BOM审批', '物料主数据',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_eng_hw' => [
            'code' => 'D-RND-P02', 'name' => '硬件工程师', 'department' => '研发部',
            'roles' => ['role_eng_hw'],
            'menu' => [
                '工作台',
                '物料主数据', 'BOM管理', 'BOM导入', '工艺路线', '工作中心', '研发项目', '我的任务',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_eng_sw' => [
            'code' => 'D-RND-P03', 'name' => '软件工程师', 'department' => '研发部',
            'roles' => ['role_eng_sw'],
            'menu' => [
                '工作台',
                '物料主数据', 'BOM管理', '研发项目', '我的任务', '文档上传', '工艺路线',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_eng_me' => [
            'code' => 'D-RND-P04', 'name' => '结构工程师', 'department' => '研发部',
            'roles' => ['role_eng_me'],
            'menu' => [
                '工作台',
                '物料主数据', 'BOM管理', 'BOM导入', '研发项目', '我的任务', '文档上传',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_pkg_designer' => [
            'code' => 'D-RND-P05', 'name' => '包装设计师', 'department' => '研发部',
            'roles' => ['role_pkg_designer'],
            'menu' => [
                '工作台',
                '物料主数据', 'BOM管理', 'BOM导入', '包装图档', '研发项目', '我的任务',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_id_designer' => [
            'code' => 'D-RND-P06', 'name' => '外观设计师', 'department' => '研发部',
            'roles' => ['role_id_designer'],
            'menu' => [
                '工作台',
                '物料主数据', 'BOM管理', '外观图档', '研发项目', '我的任务', '文档上传',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 仓储部 ──
        'role_wh_supervisor' => [
            'code' => 'D-WH-P01', 'name' => '仓库主管', 'department' => '仓储部',
            'roles' => ['role_wh_supervisor'],
            'menu' => [
                '工作台',
                '库存查询', '库存流水', '盘点单', '收货单', '发货作业', '仓库库位', '库存调整', '物料查询',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_wh_keeper' => [
            'code' => 'D-WH-P02', 'name' => '仓管', 'department' => '仓储部',
            'roles' => ['role_wh_keeper'],
            'menu' => [
                '工作台',
                '库存查询', '库存流水', '盘点录入', '收货上架', '发货作业', '领料发料', '库存调整',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_wh_receiver' => [
            'code' => 'D-WH-P03', 'name' => '收货员', 'department' => '仓储部',
            'roles' => ['role_wh_receiver'],
            'menu' => [
                '工作台', '收货登记', '送检', '物料查询',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 行政部 ──
        'role_adm_manager' => [
            'code' => 'D-ADM-P01', 'name' => '行政经理', 'department' => '行政部',
            'roles' => ['role_adm_manager'],
            'menu' => [
                '工作台',
                '组织架构', '岗位编制', '员工花名册', '请假管理', '绩效考核', '会议与语音室', '公告通知',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_hr_specialist' => [
            'code' => 'D-ADM-P02', 'name' => '人事专员', 'department' => '行政部',
            'roles' => ['role_hr_specialist'],
            'menu' => [
                '工作台',
                '员工花名册', '组织架构', '请假管理', '招聘管理', '绩效考核',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 财务部（独立部门，会计/出纳分离） ──
        'role_fin_accountant' => [
            'code' => 'D-FIN-P01', 'name' => '会计', 'department' => '财务部',
            'roles' => ['role_fin_accountant'],
            'menu' => [
                '工作台',
                '客户发票', '供应商账单', '收付款', '对账管理', '资金流水', '薪资核算', '财务报表',
                '标准成本维护', '成本差异分析', '三单匹配',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_fin_cashier' => [
            'code' => 'D-FIN-P02', 'name' => '出纳', 'department' => '财务部',
            'roles' => ['role_fin_cashier'],
            'menu' => [
                '工作台',
                '收付款执行', '现金日记账', '银行对账', '应收应付查询', '资金流水', '票据管理',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 电商部 ──
        'role_ecom_operator' => [
            'code' => 'D-ECM-P01', 'name' => '电商运营', 'department' => '电商部',
            'roles' => ['role_ecom_operator'],
            'menu' => [
                '工作台',
                '电商订单', '平台接入', '平台设置', '平台账号', '商品SPU', '商品类目', '商品品牌',
                '促销活动', '优惠券', '采购计划', '库存查询',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_ecom_cs' => [
            'code' => 'D-ECM-P02', 'name' => '电商客服', 'department' => '电商部',
            'roles' => ['role_ecom_cs'],
            'menu' => [
                '工作台', '电商订单', '售后工单', '商品资料',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

        // ── 总经理室（只读汇总） ──
        'role_gm' => [
            'code' => 'D-GM-P01', 'name' => '总经理（老板）', 'department' => '总经理室',
            'roles' => ['role_gm'],
            'menu' => [
                '工作台', '经营驾驶舱', '报表中心', '成本差异分析',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

    ],

    'compat_roles' => [

        'role_project_creator' => [          // 项目创建人
            'code' => 'COMPAT-PC', 'name' => '项目创建人', 'department' => '（兼容）',
            'roles' => ['role_project_creator'],
            'legacy_of' => 'role_rnd_manager',   // 迁移目标
            'menu' => [
                '工作台', '项目模板', '研发项目', '我的任务',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_task_executor' => [            // 项目任务执行人
            'code' => 'COMPAT-TE', 'name' => '项目任务执行人', 'department' => '（兼容）',
            'roles' => ['role_task_executor'],
            'legacy_of' => 'role_eng_hw / role_eng_sw / role_eng_me / role_pkg_designer / role_id_designer',
            'menu' => [
                '工作台', '研发项目', '我的任务', '文档上传',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_task_auditor' => [             // 项目任务审核人
            'code' => 'COMPAT-TA', 'name' => '项目任务审核人', 'department' => '（兼容）',
            'roles' => ['role_task_auditor'],
            'legacy_of' => 'role_rnd_manager（或各部门主管）',
            'menu' => [
                '工作台', '研发项目', '文档审核',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],
        'role_support_staff' => [            // 其它非项目参与人员
            'code' => 'COMPAT-SUP', 'name' => '其它非项目参与人员', 'department' => '（兼容）',
            'roles' => ['role_support_staff'],
            'legacy_of' => '支援人员（无对应岗位角色）',
            'menu' => [
                '工作台', '支援中心',
                '我的待办', '审批中心', '消息通知', '聊天室', '知识库', 'AI 助手', '个人资料',
                '资料文件', '企业微信绑定', '我的项目列表',
            ],
        ],

    ],

    /**
     * 旧角色名 → 新岗位键（迁移期兼容；S1 角色重建验收后可整段删除）
     * 现有 7 个历史角色仍被 seeder、演示账号与少量路由使用，
     * 折算到新岗位可避免"登录后菜单全空"。
     *
     * 【倒计时 · Q5 / P2-02】2026-12-31 正式停用，2026-12-17 完成回收演练；
     * 停用后本段整段删除，`erp:legacy-role:audit --strict` 必须报 0 条非白名单绑定。
     * 白名单：张三 保留 system_admin 作为系统账号留档（见 legacy_role_whitelist）。
     */
    'legacy_role_map' => [
        'system_admin' => 'role_sys_admin',
        'warehouse_supervisor' => 'role_wh_supervisor',
        'warehouse_keeper' => 'role_wh_keeper',
        'purchase_specialist' => 'role_pur_specialist',
        'sales_specialist' => 'role_sal_specialist',
        'engineer' => 'role_eng_hw',
        'quality_supervisor' => 'role_qc_manager',
    ],

    /** 旧角色停用日 / 演练日（Asia/Shanghai） */
    'legacy_role_deadline' => '2026-12-31',
    'legacy_role_rehearsal' => '2026-12-17',

    /** 白名单：账号 => 允许保留的旧角色（留档，不计入"待回收"） */
    'legacy_role_whitelist' => [
        '张三' => ['system_admin'],
    ],

    'menu_items' => [
        '工作台' => ['route' => 'home', 'permissions' => ['__auth__']],
        '我的待办' => ['route' => 'office.todos', 'permissions' => ['__auth__']],
        '审批中心' => ['route' => 'approvals.index', 'permissions' => ['__auth__']],
        '消息通知' => ['route' => 'office.notifications', 'permissions' => ['__auth__']],
        '聊天室' => ['route' => 'office.chat.rooms', 'permissions' => ['__auth__']],
        '知识库' => ['route' => 'office.knowledge', 'permissions' => ['__auth__']],
        'AI 助手' => ['route' => 'ai.chat', 'permissions' => ['ai.view']],
        '个人资料' => ['route' => 'office.profile', 'permissions' => ['__auth__']],
        '资料文件' => ['route' => 'file.resources', 'permissions' => ['__auth__']],
        '企业微信绑定' => ['route' => 'profile.wecom', 'permissions' => ['__auth__']],
        '我的项目列表' => ['route' => 'project.my', 'permissions' => ['__auth__']],
        '用户管理' => ['route' => 'users.index', 'permissions' => ['system.user.create', 'system.user.delete', 'system.user.edit', 'system.user.view']],
        '部门管理' => ['route' => 'admin.departments', 'permissions' => ['admin.department.manage']],
        '岗位管理' => ['route' => 'admin.positions', 'permissions' => ['admin.position.manage']],
        '角色管理' => ['route' => 'roles.index', 'permissions' => ['system.role.create', 'system.role.edit', 'system.role.view']],
        '权限管理' => ['route' => 'admin.permissions', 'permissions' => ['admin.permission.manage']],
        '基础数据管理' => ['route' => 'admin.master-data', 'permissions' => ['admin.masterdata.manage']],
        'AI 开通与设置' => ['route' => 'ai.guide', 'permissions' => ['ai.guide.manage', 'ai.provider.manage']],
        '企业微信设置' => ['route' => 'wecom.index', 'permissions' => ['system.wecom']],
        '系统运维' => ['route' => 'devops.index', 'permissions' => ['system.devops']],
        '系统可观测' => ['route' => 'system.observability', 'permissions' => ['system.devops']],
        '同步任务' => ['route' => 'sync.tasks', 'permissions' => ['system.sync']],
        '接口日志' => ['route' => 'platform.api-logs', 'permissions' => ['system.platform']],
        '状态映射' => ['route' => 'platform.status-mappings', 'permissions' => ['system.platform']],
        '事件队列' => ['route' => 'events.outbox', 'permissions' => ['system.event']],
        '多租户' => ['route' => 'tenants.index', 'permissions' => ['system.tenant']],
        '安全审计' => ['route' => 'security.logs', 'permissions' => ['system.security.view']],
        '生产工单' => ['route' => 'production.orders', 'permissions' => ['production.order.view']],
        '我的工单' => ['route' => 'production.orders', 'permissions' => ['production.view']],
        '我的报工' => ['route' => 'production.reports', 'permissions' => ['production.report']],
        '领料单' => ['route' => 'production.issues', 'permissions' => ['production.view']],
        '领料申请' => ['route' => 'production.issue-requests', 'permissions' => ['production.issue.request']],
        '领料发料' => ['route' => 'production.issues', 'permissions' => ['production.issue']],
        '工作中心' => ['route' => 'production.work-centers', 'permissions' => ['production.create']],
        '工艺路线' => ['route' => 'production.routings', 'permissions' => ['production.create']],
        '产能排程' => ['route' => 'production.aps', 'permissions' => ['production.create']],
        '库存盘点' => ['route' => 'production.stocktakes', 'permissions' => ['production.view']],
        '盘点单' => ['route' => 'production.stocktakes', 'permissions' => ['inventory.stocktake']],
        '盘点录入' => ['route' => 'production.stocktakes', 'permissions' => ['inventory.stocktake']],
        '库存查询' => ['route' => 'inventory.index', 'permissions' => ['inventory.view']],
        '库存流水' => ['route' => 'inventory.transactions', 'permissions' => ['inventory.transaction.view']],
        '库存调整' => ['route' => 'inventory.adjustments', 'permissions' => ['inventory.adjust']],
        '仓库库位' => ['route' => 'warehouse.index', 'permissions' => ['warehouse.edit', 'warehouse.view']],
        '发货作业' => ['route' => 'shipping.index', 'permissions' => ['sales.ship']],
        '收货单' => ['route' => 'purchase.receipts', 'permissions' => ['purchase.view', 'purchase.receive']],
        '收货登记' => ['route' => 'purchase.receipts', 'permissions' => ['purchase.receive']],
        '收货上架' => ['route' => 'purchase.receipts.pending', 'permissions' => ['purchase.receive']],
        '送检' => ['route' => 'purchase.receipts.inspect-queue', 'permissions' => ['purchase.receive', 'quality.receipt.inspect']],
        '来料收货' => ['route' => 'purchase.receipts', 'permissions' => ['purchase.view']],
        '收货检验' => ['route' => 'purchase.receipts', 'permissions' => ['purchase.view', 'quality.operate']],
        '收货进度' => ['route' => 'purchase.receipts', 'permissions' => ['purchase.view']],
        '采购申请' => ['route' => 'purchase.requisitions', 'permissions' => ['purchase.create', 'purchase.view']],
        '采购订单' => ['route' => 'purchase.orders', 'permissions' => ['purchase.order.view']],
        '采购计划' => ['route' => 'proc.plans', 'permissions' => ['procurement.operate', 'procurement.view']],
        'MRP 采购建议' => ['route' => 'proc.mrp', 'permissions' => ['procurement.operate', 'procurement.view']],
        '供应商档案' => ['route' => 'suppliers.index', 'permissions' => ['supplier.create', 'supplier.edit', 'supplier.view']],
        '供应商绩效' => ['route' => 'report.supplier', 'permissions' => ['report.supplier']],
        '客户档案' => ['route' => 'customers.index', 'permissions' => ['customer.create', 'customer.edit', 'customer.view']],
        '销售订单' => ['route' => 'sales.orders', 'permissions' => ['sales.confirm', 'sales.create', 'sales.view']],
        '报价单' => ['route' => 'sales.quotes', 'permissions' => ['sales.create', 'sales.view']],
        '客户退货' => ['route' => 'sales.returns', 'permissions' => ['sales.create', 'sales.view']],
        '销售线索' => ['route' => 'crm.leads', 'permissions' => ['crm.operate', 'crm.view']],
        '销售商机' => ['route' => 'crm.opportunities', 'permissions' => ['crm.operate', 'crm.view']],
        '跟进记录' => ['route' => 'crm.follow-ups', 'permissions' => ['crm.operate', 'crm.view']],
        '销售分析' => ['route' => 'report.sales', 'permissions' => ['report.sales']],
        '质量报表' => ['route' => 'report.quality', 'permissions' => ['report.quality']],
        '财务报表' => ['route' => 'report.finance', 'permissions' => ['report.finance']],
        '报表中心' => ['route' => 'report.center', 'permissions' => ['report.sales', 'report.quality', 'report.supplier', 'report.finance']],
        '经营驾驶舱' => ['route' => 'dashboard.exec', 'permissions' => ['dashboard.view']],
        '物料主数据' => ['route' => 'materials.index', 'permissions' => ['material.create', 'material.edit', 'material.list']],
        '物料查询' => ['route' => 'materials.index', 'permissions' => ['material.list']],
        'BOM管理' => ['route' => 'bom.index', 'permissions' => ['bom.view']],
        'BOM导入' => ['route' => 'bom.import', 'permissions' => ['bom.import']],
        'BOM审批' => ['route' => 'bom.pending', 'permissions' => ['bom.view', 'bom.approve']],
        '项目模板' => ['route' => 'project.templates', 'permissions' => ['project.template.manage']],
        '研发项目' => ['route' => 'rnd.projects', 'permissions' => ['rnd.view']],
        '我的任务' => ['route' => 'rnd.my-tasks', 'permissions' => ['rnd.view']],
        '交付物字典' => ['route' => 'rnd.task-types', 'permissions' => ['rnd.create']],
        '文档审核' => ['route' => 'rnd.documents.review', 'permissions' => ['rnd.approve']],
        '文档上传' => ['route' => 'rnd.documents', 'permissions' => ['rnd.create']],
        '包装图档' => ['route' => 'design.pkg-files', 'permissions' => ['design.file.manage']],
        '外观图档' => ['route' => 'design.id-files', 'permissions' => ['design.file.manage']],
        '检验记录' => ['route' => 'quality.inspections', 'permissions' => ['quality.inspection.view']],
        '待检任务' => ['route' => 'quality.pending', 'permissions' => ['quality.inspection.view']],
        '检验录入' => ['route' => 'quality.inspections.create', 'permissions' => ['quality.operate']],
        '检验模板' => ['route' => 'quality.templates', 'permissions' => ['quality.template.view']],
        '检验模板维护' => ['route' => 'quality.templates', 'permissions' => ['quality.template.manage']],
        '缺陷代码' => ['route' => 'quality.defects', 'permissions' => ['quality.defect.view']],
        '缺陷代码维护' => ['route' => 'quality.defects', 'permissions' => ['quality.defect.manage']],
        '在制工单' => ['route' => 'production.orders', 'permissions' => ['production.view']],
        '待出货订单' => ['route' => 'sales.orders', 'permissions' => ['sales.view']],
        '客户发票' => ['route' => 'finance.invoices', 'permissions' => ['finance.invoice.manage']],
        '供应商账单' => ['route' => 'finance.bills', 'permissions' => ['finance.bill.manage']],
        '收付款' => ['route' => 'finance.payment.execute', 'permissions' => ['finance.payment.manage']],
        '收付款执行' => ['route' => 'finance.payment.execute', 'permissions' => ['finance.payment.execute']],
        '现金日记账' => ['route' => 'finance.cashbook', 'permissions' => ['finance.cashbook.manage']],
        '银行对账' => ['route' => 'finance.bank-reconcile', 'permissions' => ['finance.bank.reconcile']],
        '应收应付查询' => ['route' => 'finance.arap', 'permissions' => ['finance.arap.view']],
        '票据管理' => ['route' => 'finance.notes', 'permissions' => ['finance.note.manage']],
        '资金流水' => ['route' => 'fin.transactions', 'permissions' => ['finance.payment.view']],
        '对账管理' => ['route' => 'fin.reconciles', 'permissions' => ['finance.reconcile.manage']],
        '薪资核算' => ['route' => 'hra.salaries', 'permissions' => ['finance.salary.manage']],
        '标准成本维护' => ['route' => 'finance.standard-costs', 'permissions' => ['cost.standard.manage']],
        '成本差异分析' => ['route' => 'finance.cost-variance', 'permissions' => ['cost.standard.view']],
        '三单匹配' => ['route' => 'finance.three-way-match', 'permissions' => ['finance.reconcile.manage']],
        '组织架构' => ['route' => 'hr.departments', 'permissions' => ['hr.operate', 'hr.view']],
        '岗位编制' => ['route' => 'hr.positions', 'permissions' => ['hr.position.manage']],
        '员工花名册' => ['route' => 'hr.employees', 'permissions' => ['hr.operate', 'hr.view']],
        '请假管理' => ['route' => 'hr.leaves', 'permissions' => ['hr.operate', 'hr.view']],
        '招聘管理' => ['route' => 'hra.recruitments', 'permissions' => ['hr.operate']],
        '绩效考核' => ['route' => 'hra.performances', 'permissions' => ['hr.operate']],
        '会议与语音室' => ['route' => 'voice.rooms', 'permissions' => ['system.voice']],
        '公告通知' => ['route' => 'announcement.index', 'permissions' => ['announcement.manage']],
        '电商订单' => ['route' => 'ecom.orders', 'permissions' => ['ecom.view']],
        '平台账号' => ['route' => 'ecom.platforms', 'permissions' => ['ecom.operate']],
        '平台接入' => ['route' => 'ecom.onboarding', 'permissions' => ['ecom.operate', 'ecom.view']],
        '平台设置' => ['route' => 'ecom.instances', 'permissions' => ['ecom.operate', 'ecom.view']],
        '商品SPU' => ['route' => 'product.spus', 'permissions' => ['product.operate']],
        '商品资料' => ['route' => 'product.spus', 'permissions' => ['product.view']],
        '商品类目' => ['route' => 'product.categories', 'permissions' => ['product.operate']],
        '商品品牌' => ['route' => 'product.brands', 'permissions' => ['product.operate']],
        '促销活动' => ['route' => 'marketing.promotions', 'permissions' => ['marketing.operate']],
        '优惠券' => ['route' => 'marketing.coupons', 'permissions' => ['marketing.operate']],
        '售后工单' => ['route' => 'marketing.tickets', 'permissions' => ['marketing.operate']],
        '支援中心' => ['route' => 'support.center', 'permissions' => ['support.view']],
    ],

    'icons' => [
        '工作台' => 'fa-gauge-high',
        '我的待办' => 'fa-list-check',
        '审批中心' => 'fa-stamp',
        '消息通知' => 'fa-bell',
        '聊天室' => 'fa-comments',
        '知识库' => 'fa-book-open',
        'AI 助手' => 'fa-robot',
        '个人资料' => 'fa-user-gear',
        '资料文件' => 'fa-folder-open',
        '企业微信绑定' => 'fa-comment-dots',
        '我的项目列表' => 'fa-diagram-project',
        '用户管理' => 'fa-users-gear',
        '部门管理' => 'fa-sitemap',
        '岗位管理' => 'fa-id-badge',
        '角色管理' => 'fa-user-shield',
        '权限管理' => 'fa-key',
        'AI 开通与设置' => 'fa-robot',
        '企业微信设置' => 'fa-comment-dots',
        '系统运维' => 'fa-server',
        '同步任务' => 'fa-rotate',
        '接口日志' => 'fa-plug',
        '状态映射' => 'fa-arrows-left-right',
        '事件队列' => 'fa-inbox',
        '多租户' => 'fa-layer-group',
        '安全审计' => 'fa-shield-halved',
        '生产工单' => 'fa-industry',
        '我的工单' => 'fa-clipboard-list',
        '我的报工' => 'fa-clock',
        '领料单' => 'fa-dolly',
        '领料申请' => 'fa-file-circle-plus',
        '库存盘点' => 'fa-clipboard-check',
        '工作中心' => 'fa-gears',
        '工艺路线' => 'fa-route',
        '产能排程' => 'fa-calendar-check',
        '物料查询' => 'fa-boxes-stacked',
        '物料主数据' => 'fa-boxes-stacked',
        '库存查询' => 'fa-warehouse',
        '库存流水' => 'fa-right-left',
        '检验记录' => 'fa-clipboard-check',
        '检验录入' => 'fa-pen-to-square',
        '待检任务' => 'fa-hourglass-half',
        '检验模板' => 'fa-file-lines',
        '检验模板维护' => 'fa-file-lines',
        '缺陷代码' => 'fa-bug',
        '缺陷代码维护' => 'fa-bug',
        '收货检验' => 'fa-truck-ramp-box',
        '供应商绩效' => 'fa-chart-line',
        '质量报表' => 'fa-chart-column',
        '来料收货' => 'fa-truck-ramp-box',
        '收货单' => 'fa-truck-ramp-box',
        'BOM管理' => 'fa-diagram-project',
        'BOM导入' => 'fa-file-import',
        '销售订单' => 'fa-cart-shopping',
        '报价单' => 'fa-file-signature',
        '客户管理' => 'fa-user-group',
        '线索商机' => 'fa-bullseye',
        '采购订单' => 'fa-file-invoice-dollar',
        '采购申请' => 'fa-file-circle-plus',
        '供应商管理' => 'fa-truck-field',
        '采购对账' => 'fa-scale-balanced',
        '发货作业台' => 'fa-truck-fast',
        '库存调整' => 'fa-sliders',
        '仓库与库位' => 'fa-warehouse',
        '出入库流水' => 'fa-right-left',
        '报表中心' => 'fa-chart-pie',
        '研发项目' => 'fa-flask',
        '项目模板' => 'fa-copy',
        '我的任务' => 'fa-list-check',
        '文档上传' => 'fa-upload',
        '文档审核' => 'fa-file-circle-check',
        '包装图档' => 'fa-box-open',
        '外观图档' => 'fa-palette',
        '设计任务' => 'fa-pen-ruler',
        '客户发票' => 'fa-file-invoice',
        '供应商账单' => 'fa-receipt',
        '收付款' => 'fa-money-bill-transfer',
        '对账管理' => 'fa-scale-balanced',
        '资金流水' => 'fa-wallet',
        '薪资核算' => 'fa-sack-dollar',
        '收付款执行' => 'fa-money-check-dollar',
        '现金日记账' => 'fa-book',
        '银行对账' => 'fa-building-columns',
        '应收应付查询' => 'fa-chart-line',
        '票据管理' => 'fa-scroll',
        '部门与编制' => 'fa-sitemap',
        '员工花名册' => 'fa-users',
        '考勤与请假' => 'fa-calendar-day',
        '我的请假' => 'fa-calendar-day',
        '公告与制度' => 'fa-bullhorn',
        '电商订单' => 'fa-shopping-bag',
        '平台接入' => 'fa-plug',
        '平台设置' => 'fa-sliders',
        '平台商品' => 'fa-tags',
        '促销活动' => 'fa-percent',
        '电商售后' => 'fa-rotate-left',
        '电商采购计划' => 'fa-file-circle-plus',
        '经营驾驶舱' => 'fa-chart-pie',
        '支援中心' => 'fa-hand-holding-heart',
        // 未列出的名称使用默认图标 fa-circle-dot
    ],
    'default_icon' => 'fa-circle-dot',
];
