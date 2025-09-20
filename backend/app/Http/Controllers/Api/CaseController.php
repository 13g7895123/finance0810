<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\CustomerCase;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomField;
use App\Models\CustomFieldValue;

class CaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // POST /api/customers/{customer}/cases
    public function storeForCustomer(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'loan_amount' => 'required|numeric|min:0',
            'lead_id' => 'sometimes|nullable|exists:customer_leads,id',
            'loan_type' => 'sometimes|nullable|string|max:50',
            'loan_term' => 'sometimes|nullable|integer|min:0',
            'interest_rate' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $case = CustomerCase::create([
            'customer_id' => $customer->id,
            'lead_id' => $data['lead_id'] ?? null,
            'case_number' => CustomerCase::generateCaseNumber(),
            'loan_amount' => $data['loan_amount'],
            'loan_type' => $data['loan_type'] ?? null,
            'loan_term' => $data['loan_term'] ?? null,
            'interest_rate' => $data['interest_rate'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        // 更新 customer 快照
        $customer->case_status = 'submitted';
        $customer->save();

        // 活動紀錄
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'activity_type' => CustomerActivity::TYPE_CASE_SUBMITTED,
            'description' => '案件送件建立',
            'new_data' => ['case_id' => $case->id, 'case_number' => $case->case_number],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'created', 'case' => $case->load('customer')], 201);
    }

    // GET /api/cases
    public function index(Request $request)
    {
        $query = CustomerCase::with(['customer']);

        if ($request->has('status')) {
            $status = $request->get('status');
            if (is_array($status)) {
                $query->whereIn('status', $status);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }

        if ($request->has('search')) {
            $s = $request->get('search');
            $query->where(function ($q) use ($s) {
                $q->where('case_number', 'like', "%$s%")
                  ->orWhereHas('customer', function ($qq) use ($s) {
                      $qq->where('name', 'like', "%$s%")
                         ->orWhere('phone', 'like', "%$s%")
                         ->orWhere('email', 'like', "%$s%");
                  });
            });
        }

        // date range (by created_at by default)
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // amount range filter
        if ($request->has('min_amount')) {
            $query->where('loan_amount', '>=', $request->get('min_amount'));
        }
        if ($request->has('max_amount')) {
            $query->where('loan_amount', '<=', $request->get('max_amount'));
        }

        // loan type filter
        if ($request->has('loan_type')) {
            $query->where('loan_type', 'like', '%'.$request->get('loan_type').'%');
        }

        $perPage = (int)($request->get('per_page', 15));
        $cases = $query->orderByDesc('created_at')->paginate($perPage);
        return response()->json($cases);
    }

    // GET /api/cases/{case}
    public function show(CustomerCase $case)
    {
        return response()->json(['case' => $case->load('customer')]);
    }

    // PUT /api/cases/{case}
    public function update(Request $request, CustomerCase $case)
    {
        $validator = Validator::make($request->all(), [
            'loan_amount' => 'sometimes|numeric|min:0',
            'loan_type' => 'sometimes|nullable|string|max:50',
            'loan_term' => 'sometimes|nullable|integer|min:0',
            'interest_rate' => 'sometimes|nullable|numeric|min:0',
            'status' => 'sometimes|in:submitted,approved,rejected,disbursed',
            'case_status' => 'sometimes|nullable|in:unassigned,valid_customer,invalid_customer,customer_service,blacklist,approved_disbursed,conditional,declined,follow_up',
            'approved_amount' => 'sometimes|nullable|numeric|min:0',
            'disbursed_amount' => 'sometimes|nullable|numeric|min:0',
            'rejection_reason' => 'sometimes|nullable|string',
            'notes' => 'sometimes|nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $old = $case->toArray();
        $data = $validator->validated();

        $statusChanged = array_key_exists('status', $data) && $data['status'] !== $case->status;
        
        // 狀態轉換規範：限制合法流轉
        if ($statusChanged) {
            $current = $case->status;
            $new = $data['status'];
            $validTransitions = [
                'submitted' => ['approved', 'rejected'],
                'approved' => ['disbursed'],
                'rejected' => [], // 婉拒後不可再變更
                'disbursed' => [], // 撥款後不可再變更
            ];
            if (!isset($validTransitions[$current]) || !in_array($new, $validTransitions[$current])) {
                return response()->json([
                    'message' => "不允許的狀態轉換：{$current} → {$new}",
                    'valid_transitions' => $validTransitions[$current] ?? []
                ], 422);
            }
        }
        if ($statusChanged) {
            switch ($data['status']) {
                case 'submitted':
                    $data['submitted_at'] = now();
                    break;
                case 'approved':
                    $data['approved_at'] = now();
                    break;
                case 'rejected':
                    $data['rejected_at'] = now();
                    break;
                case 'disbursed':
                    $data['disbursed_at'] = now();
                    break;
            }
        }

        $case->fill($data)->save();

        // 同步更新 Customer 快照
        $customer = $case->customer;
        if ($statusChanged) {
            $customer->case_status = $case->status;
        }
        if (array_key_exists('approved_amount', $data)) {
            $customer->approved_amount = $data['approved_amount'];
        }
        if (array_key_exists('disbursed_amount', $data)) {
            $customer->disbursed_amount = $data['disbursed_amount'];
        }
        $customer->save();

        // 活動紀錄
        if ($statusChanged) {
            $typeMap = [
                'submitted' => CustomerActivity::TYPE_CASE_SUBMITTED,
                'approved' => CustomerActivity::TYPE_CASE_APPROVED,
                'rejected' => CustomerActivity::TYPE_CASE_REJECTED,
                'disbursed' => CustomerActivity::TYPE_DISBURSED,
            ];
            CustomerActivity::create([
                'customer_id' => $case->customer_id,
                'user_id' => Auth::id(),
                'activity_type' => $typeMap[$case->status] ?? CustomerActivity::TYPE_UPDATED,
                'description' => "案件狀態變更為 {$case->status}",
                'old_data' => ['status' => $old['status'] ?? null],
                'new_data' => ['status' => $case->status],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return response()->json(['message' => 'updated', 'case' => $case->load('customer')]);
    }

    // PATCH /api/cases/{case}/status
    public function updateCaseStatus(Request $request, CustomerCase $case)
    {
        $validator = Validator::make($request->all(), [
            'case_status' => 'required|in:unassigned,valid_customer,invalid_customer,customer_service,blacklist,approved_disbursed,conditional,declined,follow_up',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldStatus = $case->case_status;
        $case->case_status = $request->case_status;
        $case->save();

        // 活動紀錄
        CustomerActivity::create([
            'customer_id' => $case->customer_id,
            'user_id' => Auth::id(),
            'activity_type' => CustomerActivity::TYPE_UPDATED,
            'description' => '案件狀態變更為 ' . $case->case_status_label,
            'old_data' => ['case_status' => $oldStatus],
            'new_data' => ['case_status' => $case->case_status],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Case status updated successfully',
            'case_status' => $case->case_status,
            'case_status_label' => $case->case_status_label
        ]);
    }

    // GET /api/cases/status-options
    public function getCaseStatusOptions()
    {
        return response()->json([
            'options' => CustomerCase::getCaseStatusOptions()
        ]);
    }
}
