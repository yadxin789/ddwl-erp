<?php

namespace App\Http\Controllers;

use App\Models\Crm\CrmFollowUp;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmOpportunity;
use App\Services\CrmService;
use Illuminate\Http\Request;

class CrmController extends Controller
{
    public function __construct(protected CrmService $service)
    {
    }

    public function leads(Request $request)
    {
        $leads = CrmLead::with('owner')
            ->when($request->q, fn($qb, $kw) => $qb->where('company_name', 'like', "%{$kw}%"))
            ->orderByDesc('id')->paginate(15);

        return view('crm.leads', compact('leads'));
    }

    public function leadStore(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:128'],
            'contact_name' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:128'],
            'source' => ['nullable', 'string', 'max:32'],
            'remark' => ['nullable', 'string', 'max:500'],
        ]);
        $this->service->createLead($data);

        return back()->with('success', '线索已录入。');
    }

    public function opportunities(Request $request)
    {
        $ops = CrmOpportunity::with(['lead', 'owner'])
            ->when($request->stage, fn($q, $s) => $q->where('stage', $s))
            ->orderByDesc('id')->paginate(15);
        $leads = CrmLead::where('status', '!=', 'converted')->get();

        return view('crm.opportunities', compact('ops', 'leads'));
    }

    public function opportunityStore(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'lead_id' => ['nullable', 'exists:crm_leads,id'],
            'expected_amount' => ['required', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
        ]);
        $this->service->createOpportunity($data);

        return back()->with('success', '商机已创建。');
    }

    public function followUps()
    {
        $items = CrmFollowUp::with('user')->orderByDesc('id')->paginate(20);

        return view('crm.follow-ups', compact('items'));
    }

    public function followUpStore(Request $request)
    {
        $data = $request->validate([
            'lead_id' => ['nullable', 'exists:crm_leads,id'],
            'type' => ['required', 'in:call,visit,email,meeting'],
            'content' => ['required', 'string', 'max:1000'],
        ]);
        $this->service->createFollowUp($data);

        return back()->with('success', '跟进记录已保存。');
    }
}
