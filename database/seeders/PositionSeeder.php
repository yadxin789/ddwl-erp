<?php

namespace Database\Seeders;

use App\Models\Organization\Department;
use App\Models\Organization\Position;
use Illuminate\Database\Seeder;

/**
 * 岗位 Seeder —— 唯一岗位事实源来自 config/navigation.php
 *
 * 对齐《岗位-菜单-页面规划-v1.3》§3：
 *  - 部门 10 个（新增 财务部 / 总经理室 / 电商部）；
 *  - 岗位 29 个（新增 包装设计师 / 外观设计师 / 会计 / 出纳 / 总经理 / 电商运营 / 电商客服）；
 *  - 每个岗位写入 role_names，保证"至少绑定 1 个角色"；
 *  - 历史"财务"岗（D-ADM-P03）停用并改名，避免与会计/出纳并存造成歧义。
 */
class PositionSeeder extends Seeder
{
    /** 部门顺序（编码 → 名称 / 排序） */
    private array $departments = [
        'D-PROD' => ['生产部', 1],
        'D-QC' => ['品质部', 2],
        'D-PUR' => ['采购部', 3],
        'D-SAL' => ['销售部', 4],
        'D-RND' => ['研发部', 5],
        'D-WH' => ['仓储部', 6],
        'D-ADM' => ['行政部', 7],
        'D-FIN' => ['财务部', 8],
        'D-ECM' => ['电商部', 9],
        'D-GM' => ['总经理室', 10],
        'D-SYS' => ['系统（非业务）', 11],
    ];

    /** 岗位名 → 数据范围（§3 表格最后一列） */
    private array $dataScopes = [
        '系统管理员' => '全部账号与配置（不含业务数据）',
        '生产主管' => '全部工单 / 本部门人员',
        '生产组长' => '本线工单与报工',
        '作业员' => '本人任务',
        '品质主管' => '全部检验记录 / 检验模板',
        'IQC检验员' => '来料检验',
        'IPQC检验员' => '制程检验',
        'FQC检验员' => '成品检验',
        '采购主管' => '全部采购单据 / 供应商',
        '采购专员' => '本人负责的采购单据',
        '销售主管' => '全部销售单据 / 客户',
        '销售专员' => '本人负责的客户与订单',
        '客服' => '本人跟进的客户',
        '研发经理' => '全部研发项目 / BOM 审批',
        '硬件工程师' => '本人项目与 BOM 分支',
        '软件工程师' => '本人项目与软件文档',
        '结构工程师' => '本人项目与结构件',
        '包装设计师' => '本人包装件 / 图档',
        '外观设计师' => '本人外观件 / 图档',
        '仓库主管' => '全部仓库与盘点',
        '仓管' => '本人经手的出入库',
        '收货员' => '本人收货单据',
        '行政经理' => '全部组织与制度',
        '人事专员' => '全员档案（不含薪资）',
        '会计' => '全部账务 / 薪资',
        '出纳' => '资金与票据 / 备用金',
        '电商运营' => '全部店铺与商品',
        '电商客服' => '本人接待的会话与售后',
        '总经理（老板）' => '汇总只读（无明细操作）',
    ];

    /** 岗位名 → 可执行数据范围（all / department / self / none） */
    private array $dataScopeTypes = [
        '系统管理员' => 'none',
        '生产主管' => 'all',
        '生产组长' => 'department',
        '作业员' => 'self',
        '品质主管' => 'all',
        'IQC检验员' => 'department',
        'IPQC检验员' => 'department',
        'FQC检验员' => 'department',
        '采购主管' => 'all',
        '采购专员' => 'self',
        '销售主管' => 'all',
        '销售专员' => 'self',
        '客服' => 'self',
        '研发经理' => 'all',
        '硬件工程师' => 'self',
        '软件工程师' => 'self',
        '结构工程师' => 'self',
        '包装设计师' => 'self',
        '外观设计师' => 'self',
        '仓库主管' => 'all',
        '仓管' => 'self',
        '收货员' => 'self',
        '行政经理' => 'all',
        '人事专员' => 'all',
        '会计' => 'all',
        '出纳' => 'all',
        '电商运营' => 'all',
        '电商客服' => 'self',
        '总经理（老板）' => 'all',
    ];

    public function run(): void
    {
        $departmentIds = [];
        foreach ($this->departments as $code => [$name, $sort]) {
            $department = Department::firstOrCreate(
                ['dept_code' => $code],
                ['dept_name' => $name, 'sort_order' => $sort, 'status' => 1]
            );
            if ($department->dept_name !== $name) {
                $department->update(['dept_name' => $name, 'sort_order' => $sort]);
            }
            $departmentIds[$name] = $department->id;
        }

        $definitions = config('navigation.positions', []);
        $created = 0;
        $updated = 0;

        foreach ($definitions as $key => $def) {
            $deptName = $def['department'] ?? '';
            $deptId = $departmentIds[$deptName] ?? null;
            if (! $deptId) {
                $this->command?->warn('岗位所属部门不存在：' . $def['name'] . ' → ' . $deptName);
            }

            $attributes = [
                'pos_name' => $def['name'],
                'department_id' => $deptId,
                'role_names' => array_values($def['roles'] ?? []),
                'data_scope' => $this->dataScopes[$def['name']] ?? null,
                'data_scope_type' => $this->dataScopeTypes[$def['name']] ?? 'self',
                'status' => 1,
            ];

            $position = Position::firstOrCreate(['pos_code' => $def['code']], $attributes);
            if (! $position->wasRecentlyCreated) {
                $position->update($attributes);
                $updated++;
            } else {
                $created++;
            }
        }

        // 历史"财务"岗：职责已拆到会计/出纳（D-FIN-P01/P02），停用并改名避免歧义
        $legacy = Position::where('pos_name', '财务')->first();
        if ($legacy) {
            $legacy->update([
                'pos_name' => '财务（已拆分：会计/出纳）',
                'status' => 0,
                'role_names' => [],
            ]);
            $this->command?->info('历史岗位已停用：' . $legacy->pos_code);
        }

        $this->command?->info(sprintf('岗位同步完成：新增 %d，更新 %d，部门 %d 个',
            $created, $updated, count($departmentIds)));
    }
}
