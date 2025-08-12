<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\CustomerActivity;

class BlacklistController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:api']);
    }

    // 業務提交黑名單（或解除疑慮）→ 狀態 pending_review
    public function report(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'action' => 'required|in:suspect,clear',
            'hide' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $data = $validator->validated();

        $old = $customer->only(['is_blacklisted','blacklist_status','blacklist_reason','is_hidden']);

        if ($data['action'] === 'suspect') {
            $customer->fill([
                'blacklist_status' => 'pending_review',
                'blacklist_reason' => $data['reason'],
                'blacklist_reported_by' => $user->id,
                'blacklist_reported_at' => now(),
                'is_hidden' => $request->boolean('hide', true),
            ])->save();
            $activityType = CustomerActivity::TYPE_SUSPECTED_BLACKLIST;
            $desc = '業務提報疑似黑名單：' . $data['reason'];
        } else { // clear
            $customer->fill([
                'blacklist_status' => 'cleared',
                'blacklist_reason' => $data['reason'],
                'is_hidden' => false,
            ])->save();
            $activityType = CustomerActivity::TYPE_UPDATED;
            $desc = '業務解除疑似黑名單：' . $data['reason'];
        }

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'activity_type' => $activityType,
            'description' => $desc,
            'old_data' => $old,
            'new_data' => $customer->only(['is_blacklisted','blacklist_status','blacklist_reason','is_hidden']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => '已提交', 'customer' => $customer]);
    }

    // 主管審核：通過→blacklisted 或 駁回→cleared
    public function approve(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'decision' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:500',
            'hide' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        // 權限可再加 middleware role:manager|executive|admin（此專案已配置 roles）
        // 先在 routes 加上 middleware('role:manager|executive|admin')

        $old = $customer->only(['is_blacklisted','blacklist_status','blacklist_reason','is_hidden']);
        $data = $validator->validated();

        if ($data['decision'] === 'approve') {
            $customer->fill([
                'is_blacklisted' => true,
                'blacklist_status' => 'blacklisted',
                'blacklist_reason' => $data['reason'] ?? $customer->blacklist_reason,
                'blacklist_approved_by' => $user->id,
                'blacklist_approved_at' => now(),
                'is_hidden' => $request->boolean('hide', true),
            ])->save();
            $activityType = CustomerActivity::TYPE_BLACKLISTED;
            $desc = '主管核准黑名單：' . ($data['reason'] ?? '');
        } else { // reject
            $customer->fill([
                'is_blacklisted' => false,
                'blacklist_status' => 'cleared',
                'blacklist_reason' => $data['reason'] ?? null,
                'blacklist_approved_by' => $user->id,
                'blacklist_approved_at' => now(),
                'is_hidden' => false,
            ])->save();
            $activityType = CustomerActivity::TYPE_UPDATED;
            $desc = '主管駁回黑名單：' . ($data['reason'] ?? '');
        }

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'activity_type' => $activityType,
            'description' => $desc,
            'old_data' => $old,
            'new_data' => $customer->only(['is_blacklisted','blacklist_status','blacklist_reason','is_hidden']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => '審核完成', 'customer' => $customer]);
    }

    // 切換隱藏狀態
    public function toggleHide(Request $request, Customer $customer)
    {
        $customer->is_hidden = !$customer->is_hidden;
        $customer->save();

        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'activity_type' => CustomerActivity::TYPE_UPDATED,
            'description' => '切換黑名單隱藏狀態為 ' . ($customer->is_hidden ? '隱藏' : '顯示'),
            'old_data' => null,
            'new_data' => ['is_hidden' => $customer->is_hidden],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => '已更新隱藏狀態', 'is_hidden' => $customer->is_hidden]);
    }
}
