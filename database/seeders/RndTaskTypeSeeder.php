<?php

namespace Database\Seeders;

use App\Models\Organization\Department;
use App\Models\Rnd\RndTaskType;
use Illuminate\Database\Seeder;

/**
 * 电子厂研发交付物 37 类字典
 * 按阶段分组：需求 → 硬件设计 → 结构/工艺 → 软件固件 → 测试认证 → 文档交付
 * 每条任务类型归属一个部门，创建项目页的“参与部门”即由此汇总。
 */
class RndTaskTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // 需求阶段
            ['市场调研报告', '市场与竞品调研', true, 'D-SAL'],
            ['产品需求规格书(PRD)', '产品定义与功能规格', true, 'D-RND'],
            ['可行性分析报告', '技术/成本可行性评估', false, 'D-RND'],
            ['竞品分析报告', '竞品对标分析', false, 'D-SAL'],
            // 硬件设计
            ['电路原理图', '原理设计与评审', true, 'D-RND'],
            ['PCB Layout图', 'PCB布线文件', true, 'D-RND'],
            ['BOM物料清单', '物料选型与清单', true, 'D-RND'],
            ['原理图评审记录', '设计评审输出', false, 'D-RND'],
            ['DFM可制造性报告', 'DFM/DFA 评审', false, 'D-RND'],
            ['EMC预测试报告', 'EMC 预兼容测试', false, 'D-QC'],
            ['关键物料承认书', '关键物料供应商承认', true, 'D-PUR'],
            // 结构/工艺
            ['结构设计图', 'ID/结构 2D/3D 图', true, 'D-RND'],
            ['3D结构图', '结构装配3D文件', false, 'D-RND'],
            ['模具图', '模具开模图纸', false, 'D-RND'],
            ['工艺流程图', 'PCBA/整机工艺流程', true, 'D-PROD'],
            ['SOP作业指导书', '产线作业指导书', true, 'D-PROD'],
            ['测试治具设计图', '测试/烧录治具设计', false, 'D-RND'],
            ['包装设计图', '彩盒/包装结构设计', false, 'D-RND'],
            // 软件固件
            ['固件代码', 'MCU/嵌入式固件', true, 'D-RND'],
            ['通信协议文档', '串口/USB/蓝牙/WiFi协议', false, 'D-RND'],
            ['上位机软件', '调试/配置软件', false, 'D-RND'],
            ['手机APP/小程序', '移动端应用', false, 'D-RND'],
            ['软件需求规格', '软件功能与接口规格', false, 'D-RND'],
            // 测试认证
            ['样机测试报告', '样机功能/性能测试', true, 'D-QC'],
            ['可靠性测试报告', '高低温/振动/老化测试', true, 'D-QC'],
            ['安规认证报告', 'CE/UL/3C 安规认证', true, 'D-QC'],
            ['EMC测试报告', 'EMC 正式认证测试', true, 'D-QC'],
            ['环境测试报告', '温湿度/防水防尘测试', false, 'D-QC'],
            ['跌落测试报告', '包装跌落验证', false, 'D-QC'],
            ['寿命测试报告', '产品寿命/耐久测试', false, 'D-QC'],
            ['QC检验标准', 'IQC/IPQC/OQC 检验标准', true, 'D-QC'],
            ['出货检验报告(OQC)', '出货抽检记录', false, 'D-QC'],
            // 文档交付
            ['产品规格书', '对外产品规格书', true, 'D-RND'],
            ['用户手册', '终端用户使用说明', true, 'D-RND'],
            ['维修手册', '售后维修指导', false, 'D-RND'],
            ['设计FMEA报告', '设计失效模式分析', false, 'D-RND'],
            ['项目总结报告', '项目结项总结', false, 'D-RND'],
        ];

        $deptMap = Department::query()->pluck('id', 'dept_code');

        $sort = 1;
        foreach ($types as [$name, $desc, $required, $deptCode]) {
            RndTaskType::updateOrCreate(
                ['name' => $name],
                [
                    'description' => $desc,
                    'department_id' => $deptMap[$deptCode] ?? null,
                    'is_required' => $required,
                    'sort_order' => $sort++,
                ]
            );
        }
    }
}
