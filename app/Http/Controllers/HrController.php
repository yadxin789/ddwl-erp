<?php

namespace App\Http\Controllers;

use App\Enums\HrLeaveType;
use App\Models\Hr\HrDepartment;
use App\Models\Hr\HrEmployee;
use App\Models\Hr\HrLeave;
use App\Services\HrService;
use Illuminate\Http\Request;

class HrController extends Controller
{
    public function __construct(protected HrService $service)
    {
    }

    public function departments()
    {
        $deps = HrDepartment::orderBy('code')->paginate(20);

        return view('hr.departments', compact('deps'));
    }

    public function departmentStore(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', 'unique:hr_departments,code'],
            'name' => ['required', 'string', 'max:64'],
            'manager' => ['nullable', 'string', 'max:64'],
        ]);
        $this->service->createDepartment($data);

        return back()->with('success', '部门已添加。');
    }

    public function employees(Request $request)
    {
        $emps = HrEmployee::with('department')
            ->when($request->q, fn($qb, $kw) => $qb->where('name', 'like', "%{$kw}%")->orWhere('emp_no', 'like', "%{$kw}%"))
            ->orderByDesc('id')->paginate(15);
        $deps = HrDepartment::orderBy('code')->get();

        return view('hr.employees', compact('emps', 'deps'));
    }

    public function employeeStore(Request $request)
    {
        $data = $request->validate([
            'emp_no' => ['required', 'string', 'max:32', 'unique:hr_employees,emp_no'],
            'name' => ['required', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'department_id' => ['nullable', 'exists:hr_departments,id'],
            'position' => ['nullable', 'string', 'max:64'],
            'hire_date' => ['nullable', 'date'],
        ]);
        $this->service->createEmployee($data);

        return back()->with('success', '员工已入职。');
    }

    /**
     * 岗位编制（G8）：按岗位统计在岗账号，供行政/管理员核对编制与角色绑定。
     */
    public function positions()
    {
        $positions = \App\Models\Organization\Position::query()
            ->with('department')
            ->withCount('users')
            ->orderBy('pos_code')
            ->get();

        $totalUsers = \App\Models\Organization\User::whereNotNull('position_id')->count();
        $unboundUsers = \App\Models\Organization\User::whereNull('position_id')->count();

        return view('hr.positions', compact('positions', 'totalUsers', 'unboundUsers'));
    }

    public function leaves(Request $request)
    {
        $leaves = HrLeave::with('employee')->orderByDesc('id')->paginate(15);
        $emps = HrEmployee::where('status', 'active')->get();

        return view('hr.leaves', compact('leaves', 'emps'));
    }

    public function leaveStore(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:hr_employees,id'],
            'type' => ['required', 'in:' . implode(',', array_column(HrLeaveType::cases(), 'value'))],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->createLeave($data);

        return back()->with('success', '请假申请已提交。');
    }
}
