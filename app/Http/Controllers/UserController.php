<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UserStoreRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Models\System\Tenant;
use App\Models\Organization\Department;
use App\Models\Organization\Position;
use App\Models\Organization\User;
use App\Services\OperationLogger;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->with(['department', 'position', 'roles']);

        if ($request->filled('keyword')) {
            $kw = $request->input('keyword');
            $query->where(function ($q) use ($kw) {
                $q->where('username', 'like', "%{$kw}%")
                    ->orWhere('name', 'like', "%{$kw}%")
                    ->orWhere('email', 'like', "%{$kw}%");
            });
        }

        $users = $query->orderBy('id')->paginate(20)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $departments = Department::where('status', 1)->orderBy('sort_order')->get();
        $roles = \Spatie\Permission\Models\Role::orderBy('name')->get();
        $tenants = Tenant::orderBy('code')->get();

        return view('users.create', compact('departments', 'roles', 'tenants'));
    }

    public function store(UserStoreRequest $request)
    {
        $validated = $request->validated();

        $user = User::create($validated);
        if (! empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
            app(OperationLogger::class)->record(
                'updated',
                'users',
                (string) $user->id,
                $user->identity_label,
                [
                    'before' => ['roles' => []],
                    'after' => ['roles' => $user->getRoleNames()->all()],
                ]
            );
        }

        return redirect()->route('users.index')->with('success', '用户创建成功。');
    }

    public function edit(User $user)
    {
        $departments = Department::where('status', 1)->orderBy('sort_order')->get();
        $positions = Position::where('department_id', $user->department_id)->get();
        $roles = \Spatie\Permission\Models\Role::orderBy('name')->get();
        $tenants = Tenant::orderBy('code')->get();

        return view('users.edit', compact('user', 'departments', 'positions', 'roles', 'tenants'));
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $validated = $request->validated();

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $beforeRoles = $user->getRoleNames()->all();
        $user->update($validated);
        if (array_key_exists('roles', $validated)) {
            $user->syncRoles($validated['roles'] ?? []);
            $afterRoles = $user->getRoleNames()->all();

            // 角色绑定走权限包中间表，模型事件覆盖不到，单独补流水。
            if ($beforeRoles !== $afterRoles) {
                app(OperationLogger::class)->record(
                    'updated',
                    'users',
                    (string) $user->id,
                    $user->identity_label,
                    [
                        'before' => ['roles' => $beforeRoles],
                        'after' => ['roles' => $afterRoles],
                    ]
                );
            }
        }

        return redirect()->route('users.index')->with('success', '用户更新成功。');
    }

    /**
     * 注销账号：保留历史业务数据与操作流水，释放登录账号/邮箱，供本人重新注册。
     * 不物理删除用户行，避免破坏既有单据的 user_id 追溯。
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => '不能注销当前登录账号。']);
        }

        $oldUsername = $user->username;
        $oldEmail = $user->email;
        $oldStatus = $user->status;
        $oldRoles = $user->getRoleNames()->all();

        $user->update([
            'username' => 'zz_' . $user->id . '_' . now()->format('YmdHis'),
            'email' => null,
            'status' => 0,
        ]);
        $user->syncRoles([]);

        app(OperationLogger::class)->record(
            'deleted',
            'users',
            (string) $user->id,
            $user->identity_label,
            [
                'before' => ['username' => $oldUsername, 'email' => $oldEmail, 'roles' => $oldRoles, 'status' => $oldStatus],
                'after' => ['username' => $user->username, 'email' => null, 'roles' => [], 'status' => 0, 'note' => '账号注销，历史数据保留'],
            ]
        );

        return redirect()->route('users.index')
            ->with('success', "账号「{$oldUsername}」已注销，登录名已释放；本人可使用该登录名重新注册，待管理员启用。");
    }
}
