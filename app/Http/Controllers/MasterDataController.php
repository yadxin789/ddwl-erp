<?php

namespace App\Http\Controllers;

use App\Models\Approval\ApprovalFlow;
use App\Models\Approval\ApprovalInstance;
use App\Models\Finance\PaymentTerm;
use App\Models\Finance\TaxEntity;
use App\Models\Finance\TaxRate;
use App\Models\MasterData\Material;
use App\Models\Organization\Department;
use App\Models\Organization\User;
use App\Models\Rnd\ProjectTemplate;
use App\Models\Rnd\RndProjectTask;
use App\Models\Rnd\RndTaskType;
use App\Models\System\MasterDataDomain;
use App\Models\System\NumberSequence;
use App\Models\System\Unit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

/**
 * 基础数据归口管理：
 * - 归口部门负责口径与命名标准
 * - 管理员在后台统一维护，业务角色先在各自页面只读查看
 */
class MasterDataController extends Controller
{
    public function index()
    {
        $domains = MasterDataDomain::query()
            ->with('ownerDepartment')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $departments = Department::query()
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('dept_code')
            ->get();

        return view('admin.master-data', compact('domains', 'departments'));
    }

    public function updateDomain(Request $request, MasterDataDomain $domain)
    {
        $data = $request->validate([
            'owner_department_id' => ['required', 'exists:departments,id'],
            'status' => ['required', 'in:0,1'],
        ]);

        $domain->update($data);

        return back()->with('success', '归口已更新：' . $domain->name);
    }

    public function taskTypes()
    {
        $types = RndTaskType::query()
            ->with('department')
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->paginate(50);

        $departments = Department::query()
            ->where('status', 1)
            ->orderBy('sort_order')
            ->orderBy('dept_code')
            ->get();

        $taskUsage = RndProjectTask::query()
            ->selectRaw('task_type_id, COUNT(*) as aggregate')
            ->groupBy('task_type_id')
            ->pluck('aggregate', 'task_type_id')
            ->all();

        $templateUsage = [];
        foreach (ProjectTemplate::query()->get(['id', 'name', 'steps']) as $template) {
            foreach ($template->taskConfigs() as $config) {
                $typeId = (int) ($config['task_type_id'] ?? 0);
                if ($typeId) {
                    $templateUsage[$typeId] = ($templateUsage[$typeId] ?? 0) + 1;
                }
            }
        }

        $nextSortOrder = (RndTaskType::max('sort_order') ?? 0) + 1;

        return view('admin.master-data-task-types', compact(
            'types', 'departments', 'taskUsage', 'templateUsage', 'nextSortOrder'
        ));
    }

    public function taskTypeStore(Request $request)
    {
        $data = $this->validateTaskType($request);

        RndTaskType::create([
            'code' => $this->nextTaskTypeCode(),
            'name' => $data['name'],
            'department_id' => $data['department_id'],
            'description' => $data['description'] ?? null,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => $data['sort_order'] ?? ((RndTaskType::max('sort_order') ?? 0) + 1),
            'status' => (bool) $data['status'],
        ]);

        return back()->with('success', '任务类型已创建：' . $data['name']);
    }

    public function taskTypeUpdate(Request $request, RndTaskType $taskType)
    {
        $data = $this->validateTaskType($request, $taskType->id);

        $taskType->update([
            'name' => $data['name'],
            'department_id' => $data['department_id'],
            'description' => $data['description'] ?? null,
            'is_required' => $request->boolean('is_required'),
            'sort_order' => $data['sort_order'] ?? $taskType->sort_order,
            'status' => (bool) $data['status'],
        ]);

        return back()->with('success', '任务类型已保存：' . $taskType->name);
    }

    public function taskTypeDestroy(RndTaskType $taskType)
    {
        $taskCount = RndProjectTask::where('task_type_id', $taskType->id)->count();

        $templateCount = 0;
        foreach (ProjectTemplate::query()->get(['id', 'steps']) as $template) {
            if (collect($template->taskConfigs())->contains(
                fn ($config) => (int) ($config['task_type_id'] ?? 0) === (int) $taskType->id
            )) {
                $templateCount++;
            }
        }

        if ($taskCount > 0 || $templateCount > 0) {
            return back()->withErrors([
                'task_type' => sprintf(
                    '「%s」已被 %d 个项目任务、%d 个项目模板引用，不能删除；请改为停用。',
                    $taskType->name,
                    $taskCount,
                    $templateCount
                ),
            ]);
        }

        $name = $taskType->name;
        $taskType->delete();

        return back()->with('success', '任务类型已删除：' . $name);
    }

    /* ---------- 计量单位 ---------- */

    public function units()
    {
        $units = Unit::query()->orderBy('unit_code')->get();
        $usage = Material::query()
            ->selectRaw('unit, COUNT(*) as aggregate')
            ->groupBy('unit')
            ->pluck('aggregate', 'unit')
            ->all();

        return view('admin.master-data-units', compact('units', 'usage'));
    }

    public function unitStore(Request $request)
    {
        $data = $this->validateUnit($request);

        Unit::create([
            'unit_code' => strtoupper($data['unit_code']),
            'unit_name' => $data['unit_name'],
            'conversion_factor' => $data['conversion_factor'] ?? null,
            'status' => (bool) $data['status'],
        ]);

        return back()->with('success', '计量单位已创建：' . $data['unit_name']);
    }

    public function unitUpdate(Request $request, Unit $unit)
    {
        $data = $this->validateUnit($request, $unit->id);
        $unit->update([
            'unit_code' => strtoupper($data['unit_code']),
            'unit_name' => $data['unit_name'],
            'conversion_factor' => $data['conversion_factor'] ?? null,
            'status' => (bool) $data['status'],
        ]);

        return back()->with('success', '计量单位已保存：' . $unit->unit_name);
    }

    public function unitDestroy(Unit $unit)
    {
        $used = Material::query()
            ->where('unit', $unit->unit_name)
            ->orWhere('unit', $unit->unit_code)
            ->count();

        if ($used > 0) {
            return back()->withErrors([
                'unit' => sprintf('「%s」已被 %d 条物料使用，不能删除；请改为停用。', $unit->unit_name, $used),
            ]);
        }

        $name = $unit->unit_name;
        $unit->delete();

        return back()->with('success', '计量单位已删除：' . $name);
    }

    /* ---------- 税率与结算方式 ---------- */

    public function taxTerms()
    {
        $entities = TaxEntity::query()->with('rates')->orderBy('code')->get();
        $rates = TaxRate::query()->with('entity')->orderBy('tax_entity_id')->orderBy('rate', 'desc')->get();
        $terms = PaymentTerm::query()->orderBy('code')->get();

        return view('admin.master-data-tax-terms', compact('entities', 'rates', 'terms'));
    }

    public function taxEntityStore(Request $request)
    {
        $data = $this->validateTaxEntity($request);
        TaxEntity::create($data);

        return back()->with('success', '税目已创建：' . $data['name']);
    }

    public function taxEntityUpdate(Request $request, TaxEntity $entity)
    {
        $data = $this->validateTaxEntity($request, $entity->id);
        $entity->update($data);

        return back()->with('success', '税目已保存：' . $entity->name);
    }

    public function taxEntityDestroy(TaxEntity $entity)
    {
        if ($entity->rates()->exists()) {
            return back()->withErrors(['tax_entity' => '该税目下仍有税率，不能删除；请先停用或删除税率。']);
        }

        $name = $entity->name;
        $entity->delete();

        return back()->with('success', '税目已删除：' . $name);
    }

    public function taxRateStore(Request $request)
    {
        $data = $this->validateTaxRate($request);
        TaxRate::create($data);

        return back()->with('success', '税率已添加。');
    }

    public function taxRateUpdate(Request $request, TaxRate $rate)
    {
        $data = $this->validateTaxRate($request);
        $rate->update($data);

        return back()->with('success', '税率已保存。');
    }

    public function taxRateDestroy(TaxRate $rate)
    {
        $rate->delete();

        return back()->with('success', '税率已删除。');
    }

    public function paymentTermStore(Request $request)
    {
        $data = $this->validatePaymentTerm($request);
        PaymentTerm::create($data);

        return back()->with('success', '结算方式已创建：' . $data['name']);
    }

    public function paymentTermUpdate(Request $request, PaymentTerm $term)
    {
        $data = $this->validatePaymentTerm($request, $term->id);
        $term->update($data);

        return back()->with('success', '结算方式已保存：' . $term->name);
    }

    public function paymentTermDestroy(PaymentTerm $term)
    {
        $name = $term->name;
        $term->delete();

        return back()->with('success', '结算方式已删除：' . $name);
    }

    /* ---------- 审批流程 ---------- */

    public function approvalFlows()
    {
        $flows = ApprovalFlow::query()->orderBy('id')->get();
        $instanceCounts = ApprovalInstance::query()
            ->selectRaw('flow_id, COUNT(*) as aggregate')
            ->groupBy('flow_id')
            ->pluck('aggregate', 'flow_id')
            ->all();
        $userNames = User::query()->pluck('username', 'id')->all();

        return view('admin.master-data-approval-flows', compact('flows', 'instanceCounts', 'userNames'));
    }

    public function approvalFlowCreate()
    {
        return view('admin.master-data-approval-flow-form', $this->approvalFormData(null));
    }

    public function approvalFlowEdit(ApprovalFlow $flow)
    {
        return view('admin.master-data-approval-flow-form', $this->approvalFormData($flow));
    }

    public function approvalFlowStore(Request $request)
    {
        $data = $this->validateApprovalFlow($request);

        ApprovalFlow::create([
            'flow_code' => $data['flow_code'],
            'flow_name' => $data['flow_name'],
            'business_type' => $data['business_type'],
            'steps_json' => $this->normalizeSteps($data['steps']),
            'status' => (bool) $data['status'],
        ]);

        return redirect()->route('admin.master-data.approval-flows')->with('success', '审批流程已创建。');
    }

    public function approvalFlowUpdate(Request $request, ApprovalFlow $flow)
    {
        if (ApprovalInstance::where('flow_id', $flow->id)->where('status', 'pending')->exists()) {
            return back()->withErrors([
                'flow' => '该审批流程还有待审批实例，先处理完再修改节点，避免在途单据审批链断裂。',
            ]);
        }

        $data = $this->validateApprovalFlow($request, $flow->id);
        $flow->update([
            'flow_code' => $data['flow_code'],
            'flow_name' => $data['flow_name'],
            'business_type' => $data['business_type'],
            'steps_json' => $this->normalizeSteps($data['steps']),
            'status' => (bool) $data['status'],
        ]);

        return redirect()->route('admin.master-data.approval-flows')->with('success', '审批流程已保存。');
    }

    public function approvalFlowDestroy(ApprovalFlow $flow)
    {
        $instances = ApprovalInstance::where('flow_id', $flow->id)->count();
        if ($instances > 0) {
            return back()->withErrors([
                'flow' => sprintf('该审批流程已有 %d 条审批实例，不能删除；请改为停用。', $instances),
            ]);
        }

        $name = $flow->flow_name;
        $flow->delete();

        return back()->with('success', '审批流程已删除：' . $name);
    }

    /* ---------- 单据编号规则 ---------- */

    public function numberSequences()
    {
        $rules = NumberSequence::query()->orderBy('id')->get();

        return view('admin.master-data-number-sequences', compact('rules'));
    }

    public function numberSequenceUpdate(Request $request, NumberSequence $sequence)
    {
        $data = $request->validate([
            'prefix' => ['nullable', 'string', 'max:16'],
            'date_format' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9\-_:]*$/'],
            'seq_length' => ['required', 'integer', 'min:1', 'max:12'],
            'current_seq' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:0,1'],
        ], [
            'seq_length.min' => '流水号位数至少 1 位',
            'date_format.regex' => '日期格式只能包含 Y/m/d 等格式字符、数字和 -_:',
        ]);

        $sequence->update([
            'prefix' => $data['prefix'] ?? null,
            'date_format' => $data['date_format'] ?: null,
            'seq_length' => $data['seq_length'],
            'current_seq' => $data['current_seq'],
            'description' => $data['description'] ?? null,
            'status' => (bool) $data['status'],
        ]);

        return back()->with('success', '编号规则已保存：' . $sequence->rule_code);
    }

    private function validateUnit(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'unit_code' => [
                'required', 'string', 'max:16', 'regex:/^[A-Za-z0-9_\-]+$/',
                Rule::unique('units', 'unit_code')->ignore($ignoreId),
            ],
            'unit_name' => ['required', 'string', 'max:32'],
            'conversion_factor' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:0,1'],
        ], [
            'unit_code.regex' => '单位编码只能使用字母、数字、下划线或短横线',
        ]);
    }

    private function validateTaxEntity(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('tax_entities', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:128'],
            'type' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'in:0,1'],
        ]);
        $data['status'] = (bool) $data['status'];

        return $data;
    }

    private function validateTaxRate(Request $request): array
    {
        $data = $request->validate([
            'tax_entity_id' => ['required', 'exists:tax_entities,id'],
            'tax_type' => ['required', 'string', 'max:32'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:0,1'],
        ]);
        $data['status'] = (bool) $data['status'];

        return $data;
    }

    private function validatePaymentTerm(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('payment_terms', 'code')->ignore($ignoreId),
            ],
            'name' => [
                'required', 'string', 'max:64',
                Rule::unique('payment_terms', 'name')->ignore($ignoreId),
            ],
            'days' => ['required', 'integer', 'min:0', 'max:365'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:0,1'],
        ], [
            'name.unique' => '结算方式名称已存在，客户/供应商下拉会重名',
        ]);
        $data['status'] = (bool) $data['status'];

        return $data;
    }

    private function approvalFormData(?ApprovalFlow $flow): array
    {
        return [
            'flow' => $flow,
            'users' => User::query()->where('status', 1)->orderBy('name')->get(['id', 'name', 'username', 'department_id']),
            'permissions' => Permission::query()->orderBy('name')->pluck('name'),
        ];
    }

    private function validateApprovalFlow(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'flow_code' => [
                'required', 'string', 'max:64',
                Rule::unique('approval_flows', 'flow_code')->ignore($ignoreId),
            ],
            'flow_name' => ['required', 'string', 'max:128'],
            'business_type' => [
                'required', 'string', 'max:64',
                Rule::unique('approval_flows', 'business_type')->ignore($ignoreId),
            ],
            'status' => ['required', 'in:0,1'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.name' => ['required', 'string', 'max:128'],
            'steps.*.permission' => ['required', 'string', 'exists:permissions,name'],
            'steps.*.approver_id' => ['required', 'integer', 'exists:users,id'],
            'steps.*.delegate_ids' => ['nullable', 'array'],
            'steps.*.delegate_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'steps.required' => '至少保留一个审批节点',
            'steps.*.approver_id.required' => '每个审批节点都要选择审批人',
        ]);
    }

    private function normalizeSteps(array $steps): array
    {
        return collect($steps)
            ->values()
            ->map(function (array $step, int $index) {
                $delegates = collect($step['delegate_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->reject(fn (int $id) => $id === (int) $step['approver_id'])
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'step' => $index + 1,
                    'name' => $step['name'],
                    'permission' => $step['permission'],
                    'approver_id' => (int) $step['approver_id'],
                    'delegate_ids' => $delegates,
                ];
            })
            ->all();
    }

    private function validateTaskType(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:64',
                Rule::unique('rnd_task_types', 'name')->ignore($ignoreId),
            ],
            'department_id' => ['required', 'exists:departments,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_required' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:0,1'],
        ], [
            'name.required' => '请输入任务类型名称',
            'name.unique' => '任务类型名称已存在',
            'department_id.required' => '请选择执行部门',
        ]);
    }

    private function nextTaskTypeCode(): string
    {
        $number = RndTaskType::count() + 1;
        do {
            $code = sprintf('RND-TT-%03d', $number);
            $number++;
        } while (RndTaskType::where('code', $code)->exists());

        return $code;
    }
}
