<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\CustomerLead;

class LeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // GET /api/leads
    public function index(Request $request)
    {
        $query = CustomerLead::with(['customer']);

        // 預設顯示 WP 表單進件
        if ($request->get('channel', 'wp_form')) {
            $query->where('channel', $request->get('channel', 'wp_form'));
        }

        // 搜尋：name/phone/email/line_id/source
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('line_id', 'like', "%$search%")
                    ->orWhere('source', 'like', "%$search%")
                    ->orWhere('ip_address', 'like', "%$search%");
            });
        }

        if ($request->has('is_suspected_blacklist')) {
            $query->where('is_suspected_blacklist', (bool)$request->get('is_suspected_blacklist'));
        }

        if ($request->has('website_source')) {
            $query->where('source', 'like', "%".$request->get('website_source')."%");
        }

        $perPage = (int)($request->get('per_page', 15));
        $leads = $query->orderByDesc('created_at')->paginate($perPage);
        return response()->json($leads);
    }

    // GET /api/leads/{lead}
    public function show(CustomerLead $lead)
    {
        return response()->json(['lead' => $lead->load('customer')]);
    }

    // PUT /api/leads/{lead}
    public function update(Request $request, CustomerLead $lead)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'sometimes|exists:customers,id',
            'name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|max:50',
            'email' => 'sometimes|nullable|email',
            'line_id' => 'sometimes|nullable|string|max:100',
            'source' => 'sometimes|nullable|string',
            'is_suspected_blacklist' => 'sometimes|boolean',
            'suspected_reason' => 'sometimes|nullable|string|max:500',
            'payload' => 'sometimes|array',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $lead->fill($validator->validated());
        if ($request->has('payload') && is_array($request->payload)) {
            $lead->payload = $request->payload;
        }
        $lead->save();
        return response()->json(['message' => 'updated', 'lead' => $lead]);
    }

    // DELETE /api/leads/{lead}
    public function destroy(CustomerLead $lead)
    {
        // 簡單刪除 lead，不刪除 customer
        $lead->delete();
        return response()->json(['message' => 'deleted']);
    }
}
