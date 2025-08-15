<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\BankRecord;

class BankRecordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // POST /api/bank-records
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'case_id' => 'nullable|exists:customer_cases,id',
            'bank_name' => 'required|string|max:100',
            'contact_person' => 'nullable|string|max:100',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:100',
            'communication_type' => 'nullable|in:phone,email,meeting,video_call',
            'communication_date' => 'nullable|date',
            'content' => 'required|string',
            'result' => 'nullable|string',
            'next_action' => 'nullable|string',
            'next_contact_date' => 'nullable|date',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'attachments' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        $record = BankRecord::create([
            'customer_id' => $data['customer_id'],
            'case_id' => $data['case_id'] ?? null,
            'bank_name' => $data['bank_name'],
            'contact_person' => $data['contact_person'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'communication_type' => $data['communication_type'] ?? 'phone',
            'communication_date' => $data['communication_date'] ?? now(),
            'content' => $data['content'],
            'result' => $data['result'] ?? null,
            'next_action' => $data['next_action'] ?? null,
            'next_contact_date' => $data['next_contact_date'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'attachments' => $data['attachments'] ?? null,
            'created_by' => Auth::id(),
        ]);
        return response()->json(['message' => 'created', 'record' => $record->load('customer')], 201);
    }

    // PUT /api/bank-records/{record}
    public function update(Request $request, BankRecord $record)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'sometimes|exists:customers,id',
            'case_id' => 'sometimes|nullable|exists:customer_cases,id',
            'bank_name' => 'sometimes|string|max:100',
            'contact_person' => 'sometimes|nullable|string|max:100',
            'contact_phone' => 'sometimes|nullable|string|max:20',
            'contact_email' => 'sometimes|nullable|email|max:100',
            'communication_type' => 'sometimes|in:phone,email,meeting,video_call',
            'communication_date' => 'sometimes|date',
            'content' => 'sometimes|string',
            'result' => 'sometimes|nullable|string',
            'next_action' => 'sometimes|nullable|string',
            'next_contact_date' => 'sometimes|date',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'attachments' => 'sometimes|nullable|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $record->fill($validator->validated())->save();
        return response()->json(['message' => 'updated', 'record' => $record->load('customer')]);
    }

    // GET /api/bank-records
    public function index(Request $request)
    {
        $query = BankRecord::with(['customer']);

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->get('customer_id'));
        }
        if ($request->has('case_id')) {
            $query->where('case_id', $request->get('case_id'));
        }
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }
        if ($request->has('bank_name')) {
            $query->where('bank_name', 'like', '%'.$request->get('bank_name').'%');
        }
        if ($request->has('date_from')) {
            $query->whereDate('communication_date', '>=', $request->get('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('communication_date', '<=', $request->get('date_to'));
        }
        if ($request->has('next_date_from')) {
            $query->whereDate('next_contact_date', '>=', $request->get('next_date_from'));
        }
        if ($request->has('next_date_to')) {
            $query->whereDate('next_contact_date', '<=', $request->get('next_date_to'));
        }
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('content', 'like', "%$search%")
                  ->orWhere('result', 'like', "%$search%")
                  ->orWhere('next_action', 'like', "%$search%")
                  ->orWhere('contact_person', 'like', "%$search%")
                  ->orWhere('contact_phone', 'like', "%$search%")
                  ->orWhere('contact_email', 'like', "%$search%")
                  ->orWhereHas('customer', function ($qq) use ($search) {
                      $qq->where('name', 'like', "%$search%")
                         ->orWhere('phone', 'like', "%$search%")
                         ->orWhere('email', 'like', "%$search%");
                  });
            });
        }

        $perPage = (int)($request->get('per_page', 15));
        $records = $query->orderByDesc('communication_date')->paginate($perPage);
        return response()->json($records);
    }
}
