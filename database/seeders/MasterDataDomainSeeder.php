<?php

namespace Database\Seeders;

use App\Models\Organization\Department;
use App\Models\System\MasterDataDomain;
use Illuminate\Database\Seeder;

/**
 * 基础数据归口台账初始数据。
 * 只做 firstOrCreate：后台调整过的归口部门不会被再次 seed 覆盖。
 */
class MasterDataDomainSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()->pluck('id', 'dept_code');

        $domains = [
            ['org.user', '用户账号', 'D-SYS', 'users.index', '账号、状态、所属部门与岗位；由管理员统一维护。'],
            ['org.department', '部门', 'D-SYS', 'admin.departments', '组织架构与部门启停；由管理员统一维护。'],
            ['org.position', '岗位', 'D-SYS', 'admin.positions', '岗位、角色绑定与数据范围；由管理员统一维护。'],
            ['org.role', '角色与权限', 'D-SYS', 'admin.permissions', '角色授权与权限矩阵；由管理员统一维护。'],
            ['rnd.task_type', '研发任务类型', 'D-RND', 'admin.master-data.task-types', '研发部定类型清单与命名口径，管理员在后台维护。'],
            ['master.material_category', '物料分类与子类', 'D-RND', 'materials.index', '物料分类口径由研发部负责，避免一物多类。'],
            ['master.material', '物料主数据', 'D-RND', 'materials.index', '物料编码、名称、规格、单位与分类；研发部归口。'],
            ['master.unit', '计量单位', 'D-RND', null, '物料与BOM使用的计量单位标准；待建后台维护页。'],
            ['inventory.warehouse', '仓库与库位', 'D-WH', 'warehouse.index', '仓库、库位与启停；仓储部归口。'],
            ['sales.customer', '客户主数据', 'D-SAL', 'customers.index', '客户档案与分类；销售部归口。'],
            ['purchase.supplier', '供应商主数据', 'D-PUR', 'suppliers.index', '供应商档案与分类；采购部归口。'],
            ['quality.template', '质检模板与缺陷类型', 'D-QC', 'quality.templates', '检验项目、判定标准与缺陷分类；品质部归口。'],
            ['production.work_center', '工作中心与工艺路线', 'D-PROD', 'production.work-centers', '产能、工时与工艺路线基础参数；生产部归口。'],
            ['product.category', '产品分类与品牌', 'D-ECM', 'product.categories', '产品分类、品牌与电商产品结构；电商部归口。'],
            ['finance.tax', '税率与结算方式', 'D-FIN', null, '税率、结算条件与币种口径；财务部归口，待建后台维护页。'],
            ['approval.flow', '审批流程', 'D-GM', null, '跨部门审批链与审批人规则；总经理室归口，待建后台维护页。'],
            ['system.numbering', '单据编号规则', 'D-SYS', null, '各类单据编号前缀与流水规则；管理员归口，待建后台维护页。'],
        ];

        $sort = 1;
        foreach ($domains as [$code, $name, $deptCode, $routeName, $description]) {
            MasterDataDomain::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'owner_department_id' => $departments[$deptCode] ?? null,
                    'route_name' => $routeName,
                    'description' => $description,
                    'status' => true,
                    'sort_order' => $sort,
                ]
            );
            $sort++;
        }
    }
}
