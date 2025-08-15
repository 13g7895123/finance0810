<?php

namespace App\Observers;

use App\Models\CustomerCase;

class CustomerCaseObserver
{
    public function created(CustomerCase $case): void
    {
        $this->touchLatestCaseAt($case);
    }

    public function updated(CustomerCase $case): void
    {
        $this->touchLatestCaseAt($case);
    }

    protected function touchLatestCaseAt(CustomerCase $case): void
    {
        $customer = $case->customer;
        // 取此案件的時間戳候選（狀態對應時間、否則用 created_at）
        $candidates = array_filter([
            optional($case->submitted_at)->getTimestamp(),
            optional($case->approved_at)->getTimestamp(),
            optional($case->disbursed_at)->getTimestamp(),
            optional($case->created_at)->getTimestamp(),
        ]);
        if (empty($candidates)) {
            return;
        }
        $candidateTs = max($candidates);
        $currentTs = $customer->latest_case_at ? $customer->latest_case_at->getTimestamp() : null;
        if (!$currentTs || $candidateTs > $currentTs) {
            $customer->update(['latest_case_at' => date('Y-m-d H:i:s', $candidateTs)]);
        }
    }
}
