<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        // 与参考项目 pm-server-openEuler 的 AiSeeder 保持一致：
        // 6 个 Agent / Skill 全量导入，config.tools 保留参考项目的工具注册表。
        $skills = [
            [
                'code' => 'pm-qa',
                'name' => 'PM 数据问答',
                'type' => 'agent',
                'description' => '对项目、任务、用户、部门、知识库等 PM 数据进行智能问答。',
                'config' => ['tools' => ['QueryPmOverview', 'QueryProjects', 'QueryTasks', 'QueryUsers', 'QueryDepartments', 'QueryKnowledge']],
            ],
            [
                'code' => 'pm-insight',
                'name' => '项目数据洞察',
                'type' => 'tool',
                'description' => '查询项目进度、逾期、任务完成情况，辅助管理决策。',
                'config' => ['tools' => ['QueryPmOverview', 'QueryProjects', 'QueryProjectDetail', 'QueryTasks']],
            ],
            [
                'code' => 'knowledge-retrieval',
                'name' => '知识库检索',
                'type' => 'tool',
                'description' => '检索知识库制度、流程、规范文档。',
                'config' => ['tools' => ['QueryKnowledge', 'SearchKnowledgeBase']],
            ],
            [
                'code' => 'doc-generator',
                'name' => '文档生成',
                'type' => 'doc-generator',
                'description' => '用自然语言描述数据/参数，依据模板、范例、知识库自动生成项目立项报告、周报、会议纪要、需求规格、验收报告等文档。',
                'config' => ['tools' => ['GetDocTemplate', 'SearchKnowledgeBase', 'QueryProjects', 'QueryTasks']],
            ],
            [
                'code' => 'file-retrieval',
                'name' => '文件检索',
                'type' => 'tool',
                'description' => '查询任务交付文件、资源文件及其审核状态。',
                'config' => ['tools' => ['QueryFiles']],
            ],
            [
                'code' => 'data-change',
                'name' => '数据变更助理',
                'type' => 'agent',
                'description' => '按用户指令提交任务 / 审核 / 知识库 / 支援文件的变更提案。AI 只负责起草提案，数据需你在「AI 授权中心」确认后才会真正变更。',
                'config' => ['tools' => ['ProposeTaskChange', 'ProposeTaskAudit', 'ProposeKnowledgeChange', 'ProposeKnowledgeDocChange', 'ProposeDepartmentFileChange', 'ListMyProposals', 'QueryProjects', 'QueryProjectDetail', 'QueryTasks', 'QueryKnowledge', 'QueryFiles']],
            ],
        ];

        foreach ($skills as $skill) {
            DB::table('ai_skills')->updateOrInsert(
                ['code' => $skill['code']],
                [
                    'name' => $skill['name'],
                    'description' => $skill['description'],
                    'type' => $skill['type'],
                    'config' => json_encode($skill['config'], JSON_UNESCAPED_UNICODE),
                    'enabled' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('ai_skills')->whereIn('code', [
            'pm-qa', 'pm-insight', 'knowledge-retrieval',
            'doc-generator', 'file-retrieval', 'data-change',
        ])->delete();
    }
};
