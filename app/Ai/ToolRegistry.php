<?php

namespace App\Ai;

use App\Ai\Contracts\AiTool;
use App\Ai\Tools\ClosureTool;
use App\Models\MasterData\Customer;
use App\Models\MasterData\Material;
use App\Models\Organization\Department;
use App\Models\Organization\User;
use App\Models\Inventory\InventoryStock;
use App\Models\Production\ProductionOrder;
use App\Models\Rnd\RndProject;
use App\Models\Rnd\RndProjectDocument;
use App\Models\Rnd\RndProjectTask;
use App\Models\Sales\SalesOrder;
use App\Models\System\MediaFile;
use App\Services\ApprovalService;
use App\Models\Office\TodoItem;
use App\Support\DataScopeService;
use Illuminate\Support\Carbon;

/**
 * ERP 只读工具注册表。
 *
 * 工具名尽量与参考项目 AiSeeder 的 config.tools 对齐（QueryProjects / QueryTasks /
 * QueryKnowledge / QueryFiles ...），并补充本系统特有的销售、库存、审批、生产工具。
 * 所有工具在 execute 内做权限、租户和数据范围校验，模型无法绕过。
 */
class ToolRegistry
{
    /** @var array<string, AiTool>|null */
    private ?array $tools = null;

    /**
     * @return array<string, AiTool>
     */
    public function all(): array
    {
        return $this->tools ??= $this->build();
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, AiTool>
     */
    public function forNames(array $names, bool $fallbackToAll = true): array
    {
        $all = $this->all();
        $selected = [];
        foreach ($names as $name) {
            if (isset($all[$name])) {
                $selected[$name] = $all[$name];
            }
        }

        if ($selected === [] && $fallbackToAll) {
            return $all;
        }

        return $selected;
    }

    /**
     * 转成 OpenAI / DeepSeek 的 tools 参数。
     *
     * @param  array<string, AiTool>  $tools
     */
    public function definitions(array $tools): array
    {
        $definitions = [];
        foreach ($tools as $tool) {
            $definitions[] = [
                'type' => 'function',
                'function' => [
                    'name' => $tool->name(),
                    'description' => $tool->description(),
                    'parameters' => $tool->schema(),
                ],
            ];
        }

        return $definitions;
    }

    public function execute(string $name, array $arguments, User $user): string
    {
        $tool = $this->all()[$name] ?? null;
        if (! $tool) {
            return "工具 {$name} 不存在或当前未开放。";
        }

        try {
            return $tool->execute($arguments, $user);
        } catch (\Throwable $e) {
            report($e);

            return "工具 {$name} 执行失败，请稍后重试或联系管理员。";
        }
    }

    /**
     * @return array<string, AiTool>
     */
    private function build(): array
    {
        return [
            'QueryPmOverview' => $this->overviewTool(),
            'QueryProjects' => $this->projectsTool(),
            'QueryProjectDetail' => $this->projectDetailTool(),
            'QueryTasks' => $this->tasksTool(),
            'QueryUsers' => $this->usersTool(),
            'QueryDepartments' => $this->departmentsTool(),
            'QueryKnowledge' => $this->knowledgeTool(),
            'SearchKnowledgeBase' => $this->searchKnowledgeTool(),
            'QueryFiles' => $this->filesTool(),
            'QueryResourceFiles' => $this->resourceFilesTool(),
            'QueryTodos' => $this->todosTool(),
            'QuerySalesOrders' => $this->salesOrdersTool(),
            'QueryInventory' => $this->inventoryTool(),
            'QueryPendingApprovals' => $this->pendingApprovalsTool(),
            'QueryProductionOrders' => $this->productionOrdersTool(),
            'QueryCustomers' => $this->customersTool(),
        ];
    }

    private function overviewTool(): AiTool
    {
        return new ClosureTool(
            'QueryPmOverview',
            '查询当前账号可见的经营总览：研发项目、逾期任务、本月销售额、未完工生产工单、库存物料和待我审批数量。用于回答“整体情况怎么样”“有没有风险”。不能查询明细，明细请调用对应工具。',
            $this->schema([]),
            function (array $args, User $user): string {
                $lines = ['【ERP 经营总览】'];
                $hasData = false;

                if ($user->can('rnd.view')) {
                    $hasData = true;
                    $projects = RndProject::query()->count();
                    $running = RndProject::query()->whereIn('status', ['in_progress', 'not_started'])->count();
                    $overdue = RndProjectTask::query()
                        ->whereHas('project')
                        ->where(fn ($q) => $q->where('is_overdue', true)->orWhere('end_date', '<', now()->toDateString()))
                        ->where('status', '!=', 'completed')
                        ->count();
                    $lines[] = "研发项目：{$projects} 个，其中进行中/未开始 {$running} 个；未完成任务中逾期 {$overdue} 个。";
                }

                if ($user->can('sales.view')) {
                    $hasData = true;
                    $start = now()->startOfMonth();
                    $end = now()->endOfMonth();
                    $amount = (float) SalesOrder::query()
                        ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
                        ->sum('total_amount');
                    $count = SalesOrder::query()
                        ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
                        ->count();
                    $lines[] = '本月销售：' . $count . ' 张订单，金额合计 ' . number_format($amount, 2) . ' 元。';
                }

                if ($user->can('production.view') || $user->can('production.order.view')) {
                    $hasData = true;
                    $open = ProductionOrder::query()
                        ->whereIn('status', ['planned', 'released', 'in_progress'])
                        ->count();
                    $lines[] = "未完工生产工单：{$open} 张。";
                }

                if ($user->can('inventory.view') && ! $user->tenant_id) {
                    $hasData = true;
                    $materials = InventoryStock::query()
                        ->where('status', 'available')
                        ->where('qty', '>', 0)
                        ->distinct('material_id')
                        ->count('material_id');
                    $lines[] = "有可用库存的物料：{$materials} 种。";
                }

                $pending = app(ApprovalService::class)->pendingForUser((int) $user->id)->total();
                $lines[] = "待我审批：{$pending} 单。";

                if (! $hasData) {
                    return "当前账号没有研发、销售、生产或库存的查看权限，只能查询自己的待审批数量。\n待我审批：{$pending} 单。";
                }

                return implode("\n", $lines);
            }
        );
    }

    private function projectsTool(): AiTool
    {
        return new ClosureTool(
            'QueryProjects',
            '查询研发项目列表。可按项目名称/编号/产品关键词、状态、阶段筛选，返回编号、名称、产品、版本、进度、负责人、起止日期、状态与阶段。',
            $this->schema([
                'keyword' => ['type' => 'string', 'description' => '项目名称、编号或产品关键词'],
                'status' => ['type' => 'string', 'description' => 'in_progress / overdue / completed / suspended / not_started'],
                'stage' => ['type' => 'string', 'description' => 'initiation / design / trial / mass_production'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                if (! $user->can('rnd.view')) {
                    return '当前账号没有研发项目查看权限（rnd.view），无法查询项目。';
                }

                $query = $this->visibleProjects($user)->with('creator')->latest('id');
                $keyword = trim((string) ($args['keyword'] ?? ''));
                if ($keyword !== '') {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('project_name', 'like', "%{$keyword}%")
                            ->orWhere('project_code', 'like', "%{$keyword}%")
                            ->orWhere('product_name', 'like', "%{$keyword}%")
                            ->orWhere('product_model', 'like', "%{$keyword}%");
                    });
                }
                if (! empty($args['status'])) {
                    $query->where('status', (string) $args['status']);
                }
                if (! empty($args['stage'])) {
                    $query->where('stage', (string) $args['stage']);
                }

                $rows = $query->limit($this->limit($args))->get();
                if ($rows->isEmpty()) {
                    return '未找到符合条件的研发项目。';
                }

                $out = "研发项目（{$rows->count()} 条）：\n";
                foreach ($rows as $p) {
                    $out .= "- [{$p->project_code}] {$p->project_name}（{$p->product_name} {$p->product_model}，版本 {$p->version}，进度 {$p->progress}%，负责人 "
                        . ($p->creator?->name ?? '—') . "，状态 {$this->projectStatus($p->status)}，阶段 {$p->stageLabel()}，"
                        . ($p->start_date?->format('Y-m-d') ?? '—') . ' ~ ' . ($p->end_date?->format('Y-m-d') ?? '—') . "）\n";
                }

                return rtrim($out);
            }
        );
    }

    private function projectDetailTool(): AiTool
    {
        return new ClosureTool(
            'QueryProjectDetail',
            '按项目编号或项目 ID 查询单个研发项目详情，包含项目基本信息、任务完成情况、交付物与审核状态。',
            $this->schema([
                'project_code' => ['type' => 'string', 'description' => '项目编号，如 RD-2026-001'],
                'project_id' => ['type' => 'integer', 'description' => '项目 ID；与 project_code 二者传一个即可'],
            ]),
            function (array $args, User $user): string {
                if (! $user->can('rnd.view')) {
                    return '当前账号没有研发项目查看权限（rnd.view），无法查询项目详情。';
                }

                $query = $this->visibleProjects($user)->with(['creator', 'tasks.executor', 'documents.file']);
                if (! empty($args['project_code'])) {
                    $query->where('project_code', (string) $args['project_code']);
                } elseif (! empty($args['project_id'])) {
                    $query->whereKey((int) $args['project_id']);
                } else {
                    return '请提供 project_code（项目编号）或 project_id。';
                }

                $project = $query->first();
                if (! $project) {
                    return '未找到该项目，或该项目不在当前账号的数据范围内。';
                }

                $taskTotal = $project->tasks->count();
                $taskDone = $project->tasks->where('status', 'completed')->count();
                $overdue = $project->tasks->filter(fn ($t) => ! $t->is_overdue ? $t->end_date?->isPast() : $t->is_overdue)
                    ->where('status', '!=', 'completed')->count();

                $out = "【项目 {$project->project_code} · {$project->project_name}】\n"
                    . "产品：{$project->product_name} {$project->product_model}，版本 {$project->version}\n"
                    . "进度：{$project->progress}%，状态 {$this->projectStatus($project->status)}，阶段 {$project->stageLabel()}\n"
                    . '负责人：' . ($project->creator?->name ?? '—') . '，周期：'
                    . ($project->start_date?->format('Y-m-d') ?? '—') . ' ~ ' . ($project->end_date?->format('Y-m-d') ?? '—') . "\n"
                    . "任务：共 {$taskTotal} 个，已完成 {$taskDone} 个，逾期 {$overdue} 个\n";

                if ($project->tasks->isNotEmpty()) {
                    $out .= "任务明细：\n";
                    foreach ($project->tasks->take(15) as $task) {
                        $out .= "- {$task->name}（执行人 " . ($task->executor?->name ?? '—') . "，状态 {$task->status}，截止 "
                            . ($task->end_date?->format('Y-m-d') ?? '—') . "）\n";
                    }
                }

                if ($project->documents->isNotEmpty()) {
                    $out .= "交付物：\n";
                    foreach ($project->documents->take(15) as $doc) {
                        $out .= '- ' . ($doc->file?->file_name ?? ('文件#' . $doc->file_id))
                            . "（v{$doc->version_no}，审核 {$doc->audit_status}" . ($doc->is_baseline ? '，基线' : '') . "）\n";
                    }
                }

                return rtrim($out);
            }
        );
    }

    private function tasksTool(): AiTool
    {
        return new ClosureTool(
            'QueryTasks',
            '查询研发项目任务。可按项目编号、任务名称、状态、执行人筛选；mine=true 只查当前账号执行的任务。',
            $this->schema([
                'project_code' => ['type' => 'string', 'description' => '项目编号'],
                'keyword' => ['type' => 'string', 'description' => '任务名称关键词'],
                'status' => ['type' => 'string', 'description' => 'pending / in_progress / submitted / completed 等'],
                'mine' => ['type' => 'boolean', 'description' => '是否只看当前账号执行的任务'],
                'overdue_only' => ['type' => 'boolean', 'description' => '是否只看逾期任务'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                if (! $user->can('rnd.view')) {
                    return '当前账号没有研发项目查看权限（rnd.view），无法查询任务。';
                }

                $query = RndProjectTask::with(['project', 'executor', 'taskType'])
                    ->whereHas('project', fn ($p) => $this->constrainVisibleProjects($p, $user))
                    ->latest('id');
                if (! empty($args['project_code'])) {
                    $query->whereHas('project', fn ($q) => $q->where('project_code', (string) $args['project_code']));
                }
                if (! empty($args['keyword'])) {
                    $query->where('name', 'like', '%' . trim((string) $args['keyword']) . '%');
                }
                if (! empty($args['status'])) {
                    $query->where('status', (string) $args['status']);
                }
                if (! empty($args['mine'])) {
                    $query->where('executor_id', (int) $user->id);
                }
                if (! empty($args['overdue_only'])) {
                    $query->where('status', '!=', 'completed')
                        ->where(fn ($q) => $q->where('is_overdue', true)->orWhere('end_date', '<', now()->toDateString()));
                }

                $rows = $query->limit($this->limit($args))->get();
                if ($rows->isEmpty()) {
                    return '未找到符合条件的任务。';
                }

                $out = "研发任务（{$rows->count()} 条）：\n";
                foreach ($rows as $t) {
                    $out .= "- [{$t->project?->project_code}] {$t->name}（执行人 " . ($t->executor?->name ?? '—')
                        . "，类型 " . ($t->taskType?->name ?? '—') . "，状态 {$t->status}"
                        . ($t->is_overdue ? '，已逾期' : '') . "，截止 " . ($t->end_date?->format('Y-m-d') ?? '—') . "）\n";
                }

                return rtrim($out);
            }
        );
    }

    private function usersTool(): AiTool
    {
        return new ClosureTool(
            'QueryUsers',
            '查询系统内员工列表（仅姓名、账号、部门、岗位、状态），用于确定项目执行人或文档署名。不返回手机号、邮箱、密码等敏感信息。',
            $this->schema([
                'keyword' => ['type' => 'string', 'description' => '姓名或账号关键词'],
                'department' => ['type' => 'string', 'description' => '部门名称关键词'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                if (! $user->can('rnd.view') && ! $user->can('ai.view')) {
                    return '当前账号没有查询员工目录的权限。';
                }

                $query = User::with(['department', 'position'])->where('status', 1)->orderBy('name');
                if (! empty($args['keyword'])) {
                    $kw = trim((string) $args['keyword']);
                    $query->where(fn ($q) => $q->where('name', 'like', "%{$kw}%")->orWhere('username', 'like', "%{$kw}%"));
                }
                if (! empty($args['department'])) {
                    $dept = trim((string) $args['department']);
                    $query->whereHas('department', fn ($q) => $q->where('dept_name', 'like', "%{$dept}%"));
                }

                $rows = $query->limit($this->limit($args))->get();
                if ($rows->isEmpty()) {
                    return '未找到符合条件的员工。';
                }

                $out = "员工目录（{$rows->count()} 条）：\n";
                foreach ($rows as $u) {
                    $out .= "- {$u->name}（账号 {$u->username}，部门 " . ($u->department?->dept_name ?? '—')
                        . '，岗位 ' . ($u->position?->pos_name ?? '—') . "）\n";
                }

                return rtrim($out);
            }
        );
    }

    private function departmentsTool(): AiTool
    {
        return new ClosureTool(
            'QueryDepartments',
            '查询组织部门列表，返回部门编码、名称、负责人。用于确定业务归口部门或项目承担部门。',
            $this->schema([
                'keyword' => ['type' => 'string', 'description' => '部门名称或编码关键词'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 20'],
            ]),
            function (array $args, User $user): string {
                $query = Department::with('manager')->where('status', 1)->orderBy('sort_order')->orderBy('id');
                if (! empty($args['keyword'])) {
                    $kw = trim((string) $args['keyword']);
                    $query->where(fn ($q) => $q->where('dept_name', 'like', "%{$kw}%")->orWhere('dept_code', 'like', "%{$kw}%"));
                }

                $rows = $query->limit($this->limit($args))->get();
                if ($rows->isEmpty()) {
                    return '未找到符合条件的部门。';
                }

                $out = "部门（{$rows->count()} 条）：\n";
                foreach ($rows as $d) {
                    $out .= "- [{$d->dept_code}] {$d->dept_name}（负责人 " . ($d->manager?->name ?? '—') . "）\n";
                }

                return rtrim($out);
            }
        );
    }

    private function knowledgeTool(): AiTool
    {
        return new ClosureTool(
            'QueryKnowledge',
            '查询知识库文档目录（协同办公知识库中的文件元数据）。返回文件名、分类、大小、上传人、上传时间；不返回文件正文。需要正文时请提示用户打开文档。',
            $this->schema([
                'keyword' => ['type' => 'string', 'description' => '文件名关键词'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                $query = MediaFile::with('uploader')->where('category', 'collab_doc')->orderByDesc('id');
                if (! empty($args['keyword'])) {
                    $query->where('file_name', 'like', '%' . trim((string) $args['keyword']) . '%');
                }

                $rows = $query->limit($this->limit($args))->get();
                if ($rows->isEmpty()) {
                    return '知识库中没有找到符合条件的文档。';
                }

                $out = "知识库文档（{$rows->count()} 条）：\n";
                foreach ($rows as $f) {
                    $out .= "- {$f->file_name}（" . $this->fileSize((int) $f->file_size) . '，上传人 '
                        . ($f->uploader?->name ?? '—') . '，' . ($f->created_at?->format('Y-m-d') ?? '—') . "）\n";
                }

                return rtrim($out);
            }
        );
    }

    private function searchKnowledgeTool(): AiTool
    {
        return new ClosureTool(
            'SearchKnowledgeBase',
            '在知识库文件名与研发交付物文件名中检索关键词，返回命中的文档来源。仅做目录检索，不读取文件正文；不得编造文档内容。',
            $this->schema([
                'query' => ['type' => 'string', 'description' => '检索关键词，如“采购制度”“检验规范”'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                $keyword = trim((string) ($args['query'] ?? $args['keyword'] ?? ''));
                if ($keyword === '') {
                    return '请提供检索关键词 query。';
                }

                $limit = $this->limit($args);
                $out = [];
                $files = MediaFile::with('uploader')
                    ->where(fn ($q) => $q->where('category', 'collab_doc')->orWhere('category', 'resource'))
                    ->where('file_name', 'like', "%{$keyword}%")
                    ->orderByDesc('id')->limit($limit)->get();
                foreach ($files as $f) {
                    $out[] = "- [知识库/" . ($f->category === 'resource' ? '资料库' : '知识库') . "] {$f->file_name}（上传人 "
                        . ($f->uploader?->name ?? '—') . '，' . ($f->created_at?->format('Y-m-d') ?? '—') . "）";
                }

                if ($user->can('rnd.view')) {
                    $docs = RndProjectDocument::with(['file', 'project', 'uploader'])
                        ->whereHas('file', fn ($q) => $q->where('file_name', 'like', "%{$keyword}%"))
                        ->latest('id')->limit($limit)->get();
                    foreach ($docs as $d) {
                        $out[] = "- [研发交付物/{$d->project?->project_code}] " . ($d->file?->file_name ?? ('文件#' . $d->file_id))
                            . "（v{$d->version_no}，审核 {$d->audit_status}，上传人 " . ($d->uploader?->name ?? '—') . "）";
                    }
                }

                return $out === [] ? "未在知识库或研发交付物中检索到“{$keyword}”。" : "检索结果：\n" . implode("\n", $out);
            }
        );
    }

    private function filesTool(): AiTool
    {
        return new ClosureTool(
            'QueryFiles',
            '查询研发项目交付物/图档，可按项目编号、文件名、审核状态筛选。返回文件名、版本、所属项目与任务、审核状态、上传人。',
            $this->schema([
                'project_code' => ['type' => 'string', 'description' => '项目编号'],
                'keyword' => ['type' => 'string', 'description' => '文件名关键词'],
                'audit_status' => ['type' => 'string', 'description' => 'pending / approved / rejected'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                if (! $user->can('rnd.view')) {
                    return '当前账号没有研发项目查看权限（rnd.view），无法查询交付物。';
                }

                $query = RndProjectDocument::with(['file', 'project', 'task', 'uploader'])
                    ->whereHas('project', fn ($p) => $this->constrainVisibleProjects($p, $user))
                    ->latest('id');
                if (! empty($args['project_code'])) {
                    $query->whereHas('project', fn ($q) => $q->where('project_code', (string) $args['project_code']));
                }
                if (! empty($args['keyword'])) {
                    $kw = trim((string) $args['keyword']);
                    $query->whereHas('file', fn ($q) => $q->where('file_name', 'like', "%{$kw}%"));
                }
                if (! empty($args['audit_status'])) {
                    $query->where('audit_status', (string) $args['audit_status']);
                }

                $rows = $query->limit($this->limit($args))->get();
                if ($rows->isEmpty()) {
                    return '未找到符合条件的交付物。';
                }

                $out = "研发交付物（{$rows->count()} 条）：\n";
                foreach ($rows as $d) {
                    $out .= '- ' . ($d->file?->file_name ?? ('文件#' . $d->file_id))
                        . " [{$d->project?->project_code}] v{$d->version_no}，任务 " . ($d->task?->name ?? '—')
                        . "，审核 {$d->audit_status}" . ($d->is_baseline ? '，基线' : '')
                        . '，上传人 ' . ($d->uploader?->name ?? '—') . "\n";
                }

                return rtrim($out);
            }
        );
    }

    private function resourceFilesTool(): AiTool
    {
        return new ClosureTool(
            'QueryResourceFiles',
            '查询资料文件库（模板、规格书、认证文件等共享资料）。返回文件名、大小、上传人、上传日期。',
            $this->schema([
                'keyword' => ['type' => 'string', 'description' => '文件名关键词'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                $query = MediaFile::with('uploader')->where('category', 'resource')->orderByDesc('id');
                if (! empty($args['keyword'])) {
                    $query->where('file_name', 'like', '%' . trim((string) $args['keyword']) . '%');
                }

                $rows = $query->limit($this->limit($args))->get();
                if ($rows->isEmpty()) {
                    return '资料文件库中没有找到符合条件的文件。';
                }

                $out = "资料文件（{$rows->count()} 条）：\n";
                foreach ($rows as $f) {
                    $out .= "- {$f->file_name}（" . $this->fileSize((int) $f->file_size) . '，上传人 '
                        . ($f->uploader?->name ?? '—') . '，' . ($f->created_at?->format('Y-m-d') ?? '—') . "）\n";
                }

                return rtrim($out);
            }
        );
    }

    private function todosTool(): AiTool
    {
        return new ClosureTool(
            'QueryTodos',
            '查询当前账号的待办事项（未完成）。返回标题、类型、截止时间、关联业务。只能查本人待办，不能查他人。',
            $this->schema([
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                $rows = TodoItem::query()
                    ->where('user_id', (int) $user->id)
                    ->where('is_finished', false)
                    ->orderBy('deadline')
                    ->limit($this->limit($args))
                    ->get();
                if ($rows->isEmpty()) {
                    return '当前账号没有未完成待办。';
                }

                $out = "我的待办（{$rows->count()} 条）：\n";
                foreach ($rows as $t) {
                    $out .= "- {$t->title}（类型 {$t->todo_type}，截止 " . ($t->deadline?->format('Y-m-d H:i') ?? '—')
                        . ($t->business_type ? "，关联 {$t->business_type} #{$t->business_id}" : '') . "）\n";
                }

                return rtrim($out);
            }
        );
    }

    private function salesOrdersTool(): AiTool
    {
        return new ClosureTool(
            'QuerySalesOrders',
            '查询销售订单，可按时间段、状态、客户或订单号关键词筛选。返回订单号、客户、下单日期、金额、状态、销售员。统计仅覆盖当前账号数据范围内的订单。',
            $this->schema([
                'period' => ['type' => 'string', 'description' => 'today / month / last_month / year；默认 month'],
                'from' => ['type' => 'string', 'description' => '起始日期 YYYY-MM-DD，优先于 period'],
                'to' => ['type' => 'string', 'description' => '结束日期 YYYY-MM-DD，优先于 period'],
                'status' => ['type' => 'string', 'description' => '订单状态，如 draft / confirmed / shipped / completed'],
                'keyword' => ['type' => 'string', 'description' => '订单号或客户名称关键词'],
                'limit' => ['type' => 'integer', 'description' => '明细返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                if (! $user->can('sales.view')) {
                    return '当前账号没有销售查看权限（sales.view），无法查询销售订单。';
                }

                [$start, $end, $label] = $this->dateRange($args);
                $base = SalesOrder::with(['customer', 'salesperson'])
                    ->whereBetween('order_date', [$start, $end]);
                if (! empty($args['status'])) {
                    $base->where('status', (string) $args['status']);
                }
                if (! empty($args['keyword'])) {
                    $kw = trim((string) $args['keyword']);
                    $base->where(function ($q) use ($kw) {
                        $q->where('order_no', 'like', "%{$kw}%")
                            ->orWhereHas('customer', fn ($c) => $c->where('customer_name', 'like', "%{$kw}%"));
                    });
                }

                $count = (clone $base)->count();
                $amount = (float) (clone $base)->sum('total_amount');
                $rows = $base->orderByDesc('order_date')->limit($this->limit($args))->get();

                $out = "【{$label}销售订单】\n订单数：{$count} 张，金额合计 " . number_format($amount, 2) . " 元。\n";
                if ($rows->isNotEmpty()) {
                    $out .= "明细：\n";
                    foreach ($rows as $o) {
                        $out .= "- {$o->order_no}｜" . ($o->customer?->customer_name ?? '—') . '｜'
                            . ($o->order_date?->format('Y-m-d') ?? '—') . '｜' . number_format((float) $o->total_amount, 2)
                            . " 元｜{$o->status}｜销售员 " . ($o->salesperson?->name ?? '—') . "\n";
                    }
                }

                return rtrim($out) . "\n口径：仅统计当前账号数据范围内的订单。";
            }
        );
    }

    private function inventoryTool(): AiTool
    {
        return new ClosureTool(
            'QueryInventory',
            '按物料编码或名称查询库存，返回可用量、预留量、按状态汇总。库存按当前账号可见仓库范围统计。',
            $this->schema([
                'keyword' => ['type' => 'string', 'description' => '物料编码或名称关键词，如 ACC-RM01'],
                'limit' => ['type' => 'integer', 'description' => '最多返回匹配物料数，1-20，默认 5'],
            ]),
            function (array $args, User $user): string {
                if (! $user->can('inventory.view')) {
                    return '当前账号没有库存查看权限（inventory.view），无法查询库存。';
                }
                if ($user->tenant_id) {
                    return '当前账号属于租户，而库存模块尚未纳入租户隔离，为避免跨租户数据泄露，本工具暂不开放。';
                }

                $keyword = trim((string) ($args['keyword'] ?? ''));
                if ($keyword === '') {
                    return '请提供物料编码或名称关键词 keyword。';
                }

                $materials = Material::query()
                    ->where(function ($q) use ($keyword) {
                        $q->where('material_code', 'like', "%{$keyword}%")
                            ->orWhere('name', 'like', "%{$keyword}%");
                    })
                    ->orderBy('material_code')
                    ->limit(max(1, min(20, (int) ($args['limit'] ?? 5))))
                    ->get();
                if ($materials->isEmpty()) {
                    return "没有匹配到物料（关键词：{$keyword}）。请提供更完整的物料编码，例如 ACC-RM01。";
                }

                $out = '';
                foreach ($materials as $material) {
                    $rows = InventoryStock::query()
                        ->where('material_id', $material->id)
                        ->selectRaw('status, SUM(qty) AS qty, SUM(reserved_qty) AS reserved_qty')
                        ->groupBy('status')
                        ->get();
                    $available = (float) $rows->where('status', 'available')->sum('qty');
                    $reserved = (float) $rows->sum('reserved_qty');
                    $out .= "【{$material->material_code} {$material->name}】"
                        . '可用 ' . $this->qty($available) . " {$material->unit}，已预留 " . $this->qty($reserved) . " {$material->unit}；"
                        . ($rows->isEmpty() ? "当前可见仓无库存记录\n" : '按状态：' . $rows->map(fn ($r) => "{$r->status} " . $this->qty((float) $r->qty))->implode('，') . "\n");
                }

                return rtrim($out) . "\n口径：仅统计当前账号可见仓库范围，可用量只计 available。";
            }
        );
    }

    private function pendingApprovalsTool(): AiTool
    {
        return new ClosureTool(
            'QueryPendingApprovals',
            '查询当前账号待我审批的实例（含主审与代批）。返回业务类型、业务 ID、当前步骤、申请人。只能查本人待办，不能查他人。',
            $this->schema([
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                $paginator = app(ApprovalService::class)->pendingForUser((int) $user->id);
                $total = $paginator->total();
                $rows = $paginator->getCollection()->take($this->limit($args));
                if ($total === 0) {
                    return '当前账号没有待审批事项。';
                }

                $out = "待我审批：共 {$total} 单。\n";
                foreach ($rows as $i) {
                    $out .= "- ID {$i->id}｜{$i->business_type} #{$i->business_id}｜第 {$i->current_step} 步｜申请人 "
                        . ($i->applicant?->name ?? '—') . ($i->delegated ? '｜代批' : '') . "\n";
                }

                return rtrim($out);
            }
        );
    }

    private function productionOrdersTool(): AiTool
    {
        return new ClosureTool(
            'QueryProductionOrders',
            '查询生产工单，可按状态、产品编码/名称筛选。返回工单号、产品、计划数、完工数、不良数、状态、交期。仅统计当前账号数据范围内的工单。',
            $this->schema([
                'status' => ['type' => 'string', 'description' => 'planned / released / in_progress / completed / closed'],
                'keyword' => ['type' => 'string', 'description' => '工单号或产品编码/名称关键词'],
                'open_only' => ['type' => 'boolean', 'description' => '是否只看未完工工单'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                if (! $user->can('production.view') && ! $user->can('production.order.view')) {
                    return '当前账号没有生产查看权限（production.view / production.order.view），无法查询工单。';
                }
                if ($user->tenant_id) {
                    return '当前账号属于租户，而生产模块尚未纳入租户隔离，本工具暂不开放。';
                }

                $query = ProductionOrder::with('product')->latest('id');
                if (! empty($args['status'])) {
                    $query->where('status', (string) $args['status']);
                }
                if (! empty($args['open_only'])) {
                    $query->whereIn('status', ['planned', 'released', 'in_progress']);
                }
                if (! empty($args['keyword'])) {
                    $kw = trim((string) $args['keyword']);
                    $query->where(function ($q) use ($kw) {
                        $q->where('order_no', 'like', "%{$kw}%")
                            ->orWhereHas('product', fn ($p) => $p->where('material_code', 'like', "%{$kw}%")
                                ->orWhere('name', 'like', "%{$kw}%"));
                    });
                }

                $rows = $query->limit($this->limit($args))->get();
                if ($rows->isEmpty()) {
                    return '未找到符合条件生产工单。';
                }

                $out = "生产工单（{$rows->count()} 条）：\n";
                foreach ($rows as $o) {
                    $out .= "- {$o->order_no}｜" . ($o->product?->material_code ?? '—') . ' ' . ($o->product?->name ?? '')
                        . "｜计划 {$this->qty((float) $o->plan_qty)}，完工 {$this->qty((float) $o->completed_qty)}，不良 {$this->qty((float) $o->defective_qty)}"
                        . "｜{$o->status}｜交期 " . ($o->end_date?->format('Y-m-d') ?? '—') . "\n";
                }

                return rtrim($out);
            }
        );
    }

    private function customersTool(): AiTool
    {
        return new ClosureTool(
            'QueryCustomers',
            '查询当前账号数据范围内的客户档案，返回客户编码、名称、联系人、付款条件、信用额度、状态；不返回电话、邮箱、地址等敏感字段。',
            $this->schema([
                'keyword' => ['type' => 'string', 'description' => '客户编码或名称关键词'],
                'limit' => ['type' => 'integer', 'description' => '返回条数，1-50，默认 10'],
            ]),
            function (array $args, User $user): string {
                if (! $user->can('customer.view')) {
                    return '当前账号没有客户查看权限（customer.view），无法查询客户。';
                }

                $query = Customer::query()->where('status', 1)->orderBy('customer_code');
                if (! empty($args['keyword'])) {
                    $kw = trim((string) $args['keyword']);
                    $query->where(fn ($q) => $q->where('customer_code', 'like', "%{$kw}%")
                        ->orWhere('customer_name', 'like', "%{$kw}%"));
                }

                $rows = $query->limit($this->limit($args))->get();
                if ($rows->isEmpty()) {
                    return '未找到符合条件的客户。';
                }

                $out = "客户（{$rows->count()} 条）：\n";
                foreach ($rows as $c) {
                    $out .= "- {$c->customer_code} {$c->customer_name}（联系人 " . ($c->contact_person ?? '—')
                        . '，付款条件 ' . ($c->payment_terms ?? '—') . '，信用额度 ' . number_format((float) $c->credit_limit, 2) . "）\n";
                }

                return rtrim($out);
            }
        );
    }

    /** @param array<string, array<string, mixed>> $properties */
    private function schema(array $properties, array $required = []): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    private function limit(array $args, int $default = 10, int $max = 50): int
    {
        return max(1, min($max, (int) ($args['limit'] ?? $default)));
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function dateRange(array $args): array
    {
        $from = $args['from'] ?? null;
        $to = $args['to'] ?? null;
        if ($from && $to) {
            try {
                return [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString(), '指定日期'];
            } catch (\Throwable) {
                // 落到 period 默认
            }
        }

        $now = Carbon::now();
        $period = (string) ($args['period'] ?? 'month');

        return match ($period) {
            'today' => [$now->toDateString(), $now->toDateString(), '今天'],
            'last_month' => [
                $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                '上月',
            ],
            'year' => [
                $now->copy()->startOfYear()->toDateString(),
                $now->copy()->endOfYear()->toDateString(),
                '今年',
            ],
            default => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString(), '本月'],
        };
    }

    private function projectStatus(string $status): string
    {
        return match ($status) {
            'in_progress' => '进行中',
            'overdue' => '已逾期',
            'completed' => '已完成',
            'suspended' => '已暂停',
            'not_started' => '未开始',
            default => $status ?: '—',
        };
    }

    /**
     * 研发项目的可见范围与页面“我的项目”一致：
     * - 数据范围为 all 的岗位（研发经理等）可看全部；
     * - 其他岗位只看自己创建的，或自己作为任务执行人的项目。
     */
    private function visibleProjects(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = RndProject::query();
        $this->constrainVisibleProjects($query, $user);

        return $query;
    }

    private function constrainVisibleProjects(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        $scope = app(DataScopeService::class)->forCurrentUser();
        if ($scope === DataScopeService::ALL) {
            return;
        }

        $query->withoutGlobalScope('data_scope')
            ->where(function ($q) use ($user) {
                $q->where('creator_id', (int) $user->id)
                    ->orWhereHas('tasks', fn ($t) => $t->where('executor_id', (int) $user->id));
            });
    }

    private function fileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }

    private function qty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.') ?: '0';
    }
}
