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
        $case->customer()->update([
            'latest_case_at' => now(),
        ]);
    }
}
