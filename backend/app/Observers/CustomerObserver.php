<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\CustomerLead;

class CustomerObserver
{
    /**
     * Handle the Customer "updated" event.
     */
    public function updated(Customer $customer): void
    {
        // 當客戶的 assigned_to 有變化（包括 null -> user_id、user_id -> null）
        if ($customer->wasChanged('assigned_to')) {
            CustomerLead::where('customer_id', $customer->id)
                ->update(['assigned_to' => $customer->assigned_to]);
        }
    }
}
