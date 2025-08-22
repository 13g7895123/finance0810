<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\CustomerLead;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // GET /api/leads
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = CustomerLead::with(['customer','assignee']);

        // 預設顯示 WP 表單進件
        if ($request->has('channel')) {
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

        // 篩選：案件狀態 status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // 角色權限：非 admin/executive/manager 則自動限制為只看自己（staff）
        $isPrivileged = $user && $user->hasAnyRole(['admin', 'executive', 'manager']);
        $query->when(!$isPrivileged, function ($q) use ($user) {
            $q->where('assigned_to', $user->id);
        }, function ($q) use ($request) {
            // 未指派也要能看見（例如回退後)
            if ($request->get('assigned_to') === 'all') {
                //
            } elseif ($request->get('assigned_to') === 'null') {
                $q->whereNull('assigned_to');
            } elseif ($request->get('assigned_to')) {
                $q->where('assigned_to', $request->get('assigned_to'));
            }
        });

        if ($request->has('is_suspected_blacklist')) {
            $query->where('is_suspected_blacklist', (bool)$request->get('is_suspected_blacklist'));
        }

        if ($request->has('website_source')) {
            $query->where('source', 'like', "%".$request->get('website_source')."%");
        }
        // return response()->json(
        //     $query->toRawSql()
        // );
        $perPage = (int)($request->get('per_page', 15));
        $leads = $query->orderByDesc('created_at')->paginate($perPage);
        return response()->json($leads);
    }

    // GET /api/leads/{lead}
    public function show(CustomerLead $lead)
    {
        return response()->json(['lead' => $lead->load('customer')]);
    }

    // GET /api/leads/submittable
    public function submittable(Request $request)
    {
        $user = Auth::user();
        $query = CustomerLead::with(['customer','assignee'])
            ->whereIn('status', ['intake', 'approved']);

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

        // 角色權限：非 admin/executive/manager 則只看自己（staff）
        $isPrivileged = $user && $user->hasAnyRole(['admin', 'executive', 'manager']);
        if (!$isPrivileged) {
            $query->where('assigned_to', $user->id);
        }
        // return response()->json(
        //     $query->toRawSql()
        // );
        $perPage = (int)($request->get('per_page', 15));
        $leads = $query->orderByDesc('created_at')->paginate($perPage);
        return response()->json($leads);
    }

    // PUT /api/leads/{lead}
    public function update(Request $request, CustomerLead $lead)
    {
        $user = Auth::user();
        $isPrivileged = $user && $user->hasAnyRole(['admin', 'executive', 'manager']);
        if (!$isPrivileged && $lead->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'sometimes|exists:customers,id',
            'channel' => 'sometimes|in:wp,lineoa,email,phone,wp_form',
            'email' => 'sometimes|nullable|email',
            'line_id' => 'sometimes|nullable|string|max:100',
            'ip_address' => 'sometimes|nullable|string',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
            'notes' => 'sometimes|nullable|string|max:1000',
            'payload' => 'sometimes|array',
            'status' => ['sometimes', Rule::in(LeadStatus::values())],
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        // 只填入模型存在的欄位
        $data = collect($validator->validated())
            ->only(['customer_id','assigned_to','channel','email','line_id','ip_address','status'])
            ->toArray();
        $lead->fill($data);

        // 合併 payload（保留原有）
        $payload = is_array($lead->payload) ? $lead->payload : [];
        if ($request->has('payload') && is_array($request->payload)) {
            $payload = array_merge($payload, $request->payload);
        }
        // 將未持久化的欄位也保存到 payload 內
        if ($request->filled('notes')) {
            $payload['notes'] = (string)$request->notes;
        }
        $lead->payload = $payload;

        $lead->save();
        return response()->json(['message' => 'updated', 'lead' => $lead]);
    }

    // DELETE /api/leads/{lead}
    public function destroy(CustomerLead $lead)
    {
        $user = Auth::user();
        $isPrivileged = $user && $user->hasAnyRole(['admin', 'executive', 'manager']);
        if (!$isPrivileged && $lead->assigned_to !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        // 簡單刪除 lead，不刪除 customer
        $lead->delete();
        return response()->json(['message' => 'deleted']);
    }
}
