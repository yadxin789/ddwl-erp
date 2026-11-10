<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UserStoreRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Models\Organization\Department;
use App\Models\Organization\Position;
use App\Models\Organization\User;
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

        return view('users.create', compact('departments', 'roles'));
    }

    public function store(UserStoreRequest $request)
    {
        $validated = $request->validated();

        $user = User::create($validated);
        if (! empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }

        return redirect()->route('users.index')->with('success', '用户创建成功。');
    }

    public function edit(User $user)
    {
        $departments = Department::where('status', 1)->orderBy('sort_order')->get();
        $positions = Position::where('department_id', $user->department_id)->get();
        $roles = \Spatie\Permission\Models\Role::orderBy('name')->get();

        return view('users.edit', compact('user', 'departments', 'positions', 'roles'));
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $validated = $request->validated();

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);
        if (array_key_exists('roles', $validated)) {
            $user->syncRoles($validated['roles'] ?? []);
        }

        return redirect()->route('users.index')->with('success', '用户更新成功。');
    }
}
