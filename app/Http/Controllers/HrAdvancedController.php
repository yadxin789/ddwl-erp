<?php

namespace App\Http\Controllers;

use App\Models\Hr\HrRecruitment;
use App\Models\Hr\HrSalary;
use App\Models\Hr\HrPerformance;
use App\Models\Hr\HrEmployee;
use App\Support\NumberGenerator;
use Illuminate\Http\Request;

class HrAdvancedController extends Controller
{
    public function recruitments()
    {
        $rows = HrRecruitment::orderByDesc('id')->paginate(15);

        return view('hr-adv.recruitments', compact('rows'));
    }

    public function recruitmentStore(Request $request)
    {
        $data = $request->validate([
            'position' => ['required', 'string', 'max:64'],
            'headcount' => ['required', 'integer', 'min:1'],
            'department' => ['nullable', 'string', 'max:64'],
            'requirement' => ['nullable', 'string', 'max:500'],
        ]);
        $data['req_no'] = NumberGenerator::generate('req');
        $data['status'] = 'open';
        HrRecruitment::create($data);

        return back()->with('success', '招聘需求已发布。');
    }

    public function salaries()
    {
        $rows = HrSalary::with('employee')->orderByDesc('id')->paginate(15);
        $emps = HrEmployee::where('status', 'active')->get();

        return view('hr-adv.salaries', compact('rows', 'emps'));
    }

    public function salaryStore(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:hr_employees,id'],
            'period' => ['required', 'string', 'max:8'],
            'base' => ['required', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'deduction' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['net'] = $data['base'] + ($data['bonus'] ?? 0) - ($data['deduction'] ?? 0);
        HrSalary::create($data);

        return back()->with('success', '工资条已生成。');
    }

    public function performances()
    {
        $rows = HrPerformance::with('employee')->orderByDesc('id')->paginate(15);
        $emps = HrEmployee::where('status', 'active')->get();

        return view('hr-adv.performances', compact('rows', 'emps'));
    }

    public function performanceStore(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:hr_employees,id'],
            'period' => ['required', 'string', 'max:8'],
            'score' => ['required', 'integer', 'min:0', 'max:100'],
            'grade' => ['nullable', 'string', 'max:8'],
        ]);
        HrPerformance::create($data);

        return back()->with('success', '绩效已录入。');
    }
}
