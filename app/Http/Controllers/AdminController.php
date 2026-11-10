<?php

namespace App\Http\Controllers;

use App\Models\Ai\AiProvider;
use App\Models\Organization\Department;
use App\Models\Organization\Position;
use App\Models\Organization\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 后台管理（只对系统管理员开放）：部门 / 岗位 / 角色权限 / AI 开通引导
 * 原则（v1.3 §2.2）：管理员只管系统运维与后台管理，不参与任何业务单据。
 */
class AdminController extends Controller
{
    /* ---------- 部门管理 ---------- */

    public function departments()
    {
        $departments = Department::query()
            ->withCount('positions')
            ->orderBy('sort_order')
            ->orderBy('dept_code')
            ->get();

        return view('admin.departments', compact('departments'));
    }

    public function departmentStore(Request $request)
    {
        $data = $request->validate([
            'dept_code' => ['required', 'string', 'max:16', 'unique:departments,dept_code'],
            'dept_name' => ['required', 'string', 'max:64'],
            'parent_id' => ['nullable', 'exists:departments,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        Department::create([
            'dept_code' => strtoupper(trim($data['dept_code'])),
            'dept_name' => $data['dept_name'],
            'parent_id' => $data['parent_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => 1,
        ]);

        return back()->with('success', '部门已创建：' . $data['dept_name']);
    }

    public function departmentUpdate(Request $request, Department $department)
    {
        $data = $request->validate([
            'dept_name' => ['required', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:0,1'],
        ]);

        $department->update($data);

        return back()->with('success', '部门已更新：' . $department->dept_name);
    }

    /* ---------- 岗位管理 ---------- */

    public function positions()
    {
        $positions = Position::query()
            ->with('department')
            ->orderBy('pos_code')
            ->get();
        $departments = Department::orderBy('sort_order')->get();
        $roles = Role::orderBy('name')->pluck('name');

        return view('admin.positions', compact('positions', 'departments', 'roles'));
    }

    public function positionStore(Request $request)
    {
        $data = $this->validatePosition($request);

        Position::create([
            'pos_code' => strtoupper(trim($data['pos_code'])),
            'pos_name' => $data['pos_name'],
            'department_id' => $data['department_id'],
            'role_names' => array_values(array_unique($data['role_names'])),
            'data_scope' => $data['data_scope'] ?? null,
            'data_scope_type' => $data['data_scope_type'],
            'status' => $data['status'],
        ]);

        return back()->with('success', '岗位已创建：' . $data['pos_name']);
    }

    public function positionUpdate(Request $request, Position $position)
    {
        $data = $this->validatePosition($request, $position->id);

        $position->update([
            'pos_code' => strtoupper(trim($data['pos_code'])),
            'pos_name' => $data['pos_name'],
            'department_id' => $data['department_id'],
            // 硬约束：岗位必须至少绑定 1 个角色（v1.3 §3.2）
            'role_names' => array_values(array_unique($data['role_names'])),
            'data_scope' => $data['data_scope'] ?? null,
            'data_scope_type' => $data['data_scope_type'],
            'status' => $data['status'],
        ]);

        return back()->with('success', '岗位已更新：' . $position->pos_name);
    }

    private function validatePosition(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'pos_code' => ['required', 'string', 'max:16', Rule::unique('positions', 'pos_code')->ignore($ignoreId)],
            'pos_name' => ['required', 'string', 'max:64'],
            'department_id' => ['required', 'exists:departments,id'],
            'role_names' => ['required', 'array', 'min:1'],
            'role_names.*' => ['string', Rule::exists('roles', 'name')],
            'data_scope' => ['nullable', 'string', 'max:120'],
            'data_scope_type' => ['required', 'in:all,department,self,none'],
            'status' => ['required', 'in:0,1'],
        ], [], [
            'role_names' => '绑定角色',
            'department_id' => '所属部门',
        ]);
    }

    /* ---------- 角色与权限管理 ---------- */

    public function permissions()
    {
        $roles = Role::query()->withCount('permissions')->orderBy('name')->get();
        $permissionGroups = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->groupBy(fn ($name) => explode('.', $name)[0]);

        $roleNames = Role::pluck('name')->all();
        $positionCoverage = Position::query()
            ->where('status', 1)
            ->get(['pos_code', 'pos_name', 'role_names'])
            ->map(fn ($p) => [
                'pos_code' => $p->pos_code,
                'pos_name' => $p->pos_name,
                'bound' => array_values(array_intersect($p->role_names ?? [], $roleNames)),
            ]);

        return view('admin.permissions', compact('roles', 'permissionGroups', 'positionCoverage'));
    }

    public function rolePermissions(Role $role)
    {
        $rolePermissions = $role->permissions->pluck('name')->all();
        $permissionGroups = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->groupBy(fn ($name) => explode('.', $name)[0]);

        return view('admin.role-permissions', compact('role', 'rolePermissions', 'permissionGroups'));
    }

    public function rolePermissionsUpdate(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        // 系统管理员：业务权限一律不允许再赋回来（铁律的可执行边界）
        $systemWhitelist = [
            'system.user.view', 'system.user.create', 'system.user.edit', 'system.user.delete',
            'system.role.view', 'system.role.create', 'system.role.edit', 'system.role.delete',
            'system.department.view', 'system.department.create', 'system.department.edit',
            'system.position.view', 'system.position.create', 'system.position.edit',
            'system.setting.view', 'system.setting.edit', 'system.audit.view',
            'system.devops', 'system.tenant', 'system.wecom', 'system.sync', 'system.platform',
            'system.event', 'system.security.view', 'system.approval.view', 'system.voice',
            'ai.view', 'ai.provider.manage', 'ai.guide.manage',
            'admin.department.manage', 'admin.position.manage', 'admin.permission.manage',
        ];

        $requested = $data['permissions'] ?? [];

        if (in_array($role->name, ['role_sys_admin', 'system_admin'], true)) {
            $blocked = array_diff($requested, $systemWhitelist);
            if ($blocked) {
                return back()->withErrors([
                    'permissions' => '系统管理员不允许持有业务权限：' . implode('、', array_slice($blocked, 0, 8)),
                ]);
            }
        }

        $role->syncPermissions($requested);

        return redirect()->route('admin.permissions')->with('success', '角色权限已保存：' . $role->name);
    }

    /* ---------- AI 开通引导与设置 ---------- */

    public function aiGuide()
    {
        $providers = AiProvider::orderBy('id')->get();

        return view('admin.ai-guide', compact('providers'));
    }

    public function aiProviderStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'provider_code' => ['required', 'string', 'max:32', 'unique:ai_providers,provider_code'],
            'api_base' => ['required', 'string', 'max:200'],
            'model' => ['nullable', 'string', 'max:64'],
            'api_key' => ['required', 'string', 'max:200'],
        ]);

        $provider = new AiProvider($data);
        $provider->api_key = $data['api_key'];   // 加密字段不走批量赋值
        $provider->status = true;
        $provider->save();

        return back()->with('success', 'AI 提供方已接入：' . $data['name']);
    }

    public function aiProviderToggle(AiProvider $provider)
    {
        $provider->update(['status' => ! $provider->status]);

        return back()->with('success', sprintf('%s 已%s', $provider->name, $provider->status ? '启用' : '停用'));
    }
}
