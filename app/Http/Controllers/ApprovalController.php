<?php

namespace App\Http\Controllers;

use App\Models\Approval\ApprovalInstance;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    public function __construct(protected ApprovalService $service)
    {
    }

    public function index()
    {
        // P1-01：只显示"待我审"（当前步骤主审人 + 代批人，且申请人本人不显示自己的单）
        $instances = $this->service->pendingForUser((int) Auth::id());

        return view('office.approvals', compact('instances'));
    }

    public function decide(Request $request, ApprovalInstance $approval)
    {
        $request->validate(['pass' => ['required', 'boolean'], 'remark' => ['nullable', 'string', 'max:255']]);
        try {
            $this->service->decide($approval->id, (bool) $request->input('pass'), $request->input('remark'));

            return back()->with('success', $request->boolean('pass') ? '审批已通过。' : '审批已驳回。');
        } catch (\DomainException|\InvalidArgumentException $e) {
            $message = $e->getMessage();

            // P1-09：越权审批/自批属安全边界，直接 403（不再用 302 掩盖），便于 SoD 回归断言
            if (in_array($message, ['您不是该步骤的指定审批人', '申请人不得审批自己提交的单据'], true)) {
                abort(403, $message);
            }

            return back()->withErrors(['approval' => $message]);
        }
    }
}
