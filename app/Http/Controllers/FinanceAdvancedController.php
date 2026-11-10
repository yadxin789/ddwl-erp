<?php

namespace App\Http\Controllers;

use App\Models\Finance\FinancialTransaction;
use App\Models\Finance\FinanceReconcile;
use App\Services\NumberGenerator;
use Illuminate\Http\Request;

class FinanceAdvancedController extends Controller
{
    public function reconciles()
    {
        $rows = FinanceReconcile::orderByDesc('id')->paginate(15);

        return view('finance-adv.reconciles', compact('rows'));
    }

    public function reconcileStore(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'date'],
            'type' => ['required', 'in:bank,customer,supplier'],
            'book_amount' => ['required', 'numeric'],
            'actual_amount' => ['required', 'numeric'],
        ]);
        $data['reconcile_no'] = NumberGenerator::generate('reconcile');
        $data['diff_amount'] = $data['book_amount'] - $data['actual_amount'];
        $data['status'] = 'draft';
        FinanceReconcile::create($data);

        return back()->with('success', '对账单已创建。');
    }

    public function transactions()
    {
        $txs = FinancialTransaction::orderByDesc('id')->paginate(20);

        return view('finance-adv.transactions', compact('txs'));
    }

    public function transactionStore(Request $request)
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric'],
            'direction' => ['required', 'in:debit,credit'],
            'summary' => ['nullable', 'string', 'max:256'],
            'tx_date' => ['required', 'date'],
        ]);
        $data['tx_no'] = NumberGenerator::generate('fatx');
        FinancialTransaction::create($data);

        return back()->with('success', '财务流水已登记。');
    }
}
