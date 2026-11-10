<?php

namespace Database\Seeders;

use App\Models\Rnd\RndTaskType;
use Illuminate\Database\Seeder;

/**
 * 电子厂研发交付物 37 类字典
 * 按阶段分组：需求 → 硬件设计 → 结构/工艺 → 软件固件 → 测试认证 → 文档交付
 */
class RndTaskTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // 需求阶段
            ['市场调研报告', '市场与竞品调研', true],
            ['产品需求规格书(PRD)', '产品定义与功能规格', true],
            ['可行性分析报告', '技术/成本可行性评估', false],
            ['竞品分析报告', '竞品对标分析', false],
            // 硬件设计
            ['电路原理图', '原理设计与评审', true],
            ['PCB Layout图', 'PCB布线文件', true],
            ['BOM物料清单', '物料选型与清单', true],
            ['原理图评审记录', '设计评审输出', false],
            ['DFM可制造性报告', 'DFM/DFA 评审', false],
            ['EMC预测试报告', 'EMC 预兼容测试', false],
            ['关键物料承认书', '关键物料供应商承认', true],
            // 结构/工艺
            ['结构设计图', 'ID/结构 2D/3D 图', true],
            ['3D结构图', '结构装配3D文件', false],
            ['模具图', '模具开模图纸', false],
            ['工艺流程图', 'PCBA/整机工艺流程', true],
            ['SOP作业指导书', '产线作业指导书', true],
            ['测试治具设计图', '测试/烧录治具设计', false],
            ['包装设计图', '彩盒/包装结构设计', false],
            // 软件固件
            ['固件代码', 'MCU/嵌入式固件', true],
            ['通信协议文档', '串口/USB/蓝牙/WiFi协议', false],
            ['上位机软件', '调试/配置软件', false],
            ['手机APP/小程序', '移动端应用', false],
            ['软件需求规格', '软件功能与接口规格', false],
            // 测试认证
            ['样机测试报告', '样机功能/性能测试', true],
            ['可靠性测试报告', '高低温/振动/老化测试', true],
            ['安规认证报告', 'CE/UL/3C 安规认证', true],
            ['EMC测试报告', 'EMC 正式认证测试', true],
            ['环境测试报告', '温湿度/防水防尘测试', false],
            ['跌落测试报告', '包装跌落验证', false],
            ['寿命测试报告', '产品寿命/耐久测试', false],
            ['QC检验标准', 'IQC/IPQC/OQC 检验标准', true],
            ['出货检验报告(OQC)', '出货抽检记录', false],
            // 文档交付
            ['产品规格书', '对外产品规格书', true],
            ['用户手册', '终端用户使用说明', true],
            ['维修手册', '售后维修指导', false],
            ['设计FMEA报告', '设计失效模式分析', false],
            ['项目总结报告', '项目结项总结', false],
        ];

        $sort = 1;
        foreach ($types as [$name, $desc, $required]) {
            RndTaskType::updateOrCreate(
                ['name' => $name],
                ['description' => $desc, 'is_required' => $required, 'sort_order' => $sort++]
            );
        }
    }
}
