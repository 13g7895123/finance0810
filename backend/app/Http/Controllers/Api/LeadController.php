<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\CustomerLead;
use App\Models\LineUser;
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
        
        // Point 37: 加載LINE用戶資訊
        $leads->getCollection()->transform(function ($lead) {
            // 檢查line_id是否存在，並判斷是否為user_id格式
            if ($lead->line_id) {
                $lineId = $lead->line_id;
                
                // Point 37: 區分user_id與line_id格式
                $lead->is_line_user_id = $this->isLineUserId($lineId);
                
                if ($lead->is_line_user_id) {
                    // 如果是user_id格式，查詢line_users表獲取完整資料
                    $lineUser = LineUser::where('line_user_id', $lineId)->first();
                    if ($lineUser) {
                        $lead->line_user_info = [
                            'id' => $lineUser->id,
                            'display_name' => $lineUser->getDisplayName(), // Point 39: 優先顯示業務名稱
                            'api_display_name' => $lineUser->getApiDisplayName(), // Point 39: API原始名稱（僅供參考）
                            'display_name_original' => $lineUser->display_name_original,
                            'business_display_name' => $lineUser->business_display_name,
                            'has_custom_name' => $lineUser->hasCustomBusinessName(),
                            'picture_url' => $lineUser->picture_url,
                            'status_message' => $lineUser->status_message,
                            'profile_completeness' => $lineUser->getProfileCompletenessScore(),
                            'is_friend' => $lineUser->is_friend,
                            'editable_name' => $lineUser->getDisplayName(), // Point 39: 業務可編輯的名稱
                            'business_name_updated_by' => $lineUser->business_name_updated_by,
                            'business_name_updated_at' => $lineUser->business_name_updated_at
                        ];
                    } else {
                        $lead->line_user_info = null;
                    }
                } else {
                    // 如果是一般line_id，保持原有顯示
                    $lead->line_user_info = null;
                }
            } else {
                $lead->is_line_user_id = false;
                $lead->line_user_info = null;
            }
            
            return $lead;
        });
        
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
    
    /**
     * Point 37: 判斷是否為LINE user_id格式
     * LINE user_id 格式：U + 32位字母數字，總長度33
     */
    private function isLineUserId($lineId)
    {
        // LINE user_id格式檢查：以U開頭，總長度33字符，只包含字母數字
        return preg_match('/^U[a-f0-9]{32}$/i', $lineId) === 1;
    }
    
    /**
     * Point 39: 更新LINE用戶的業務顯示名稱（不影響API原始資料）
     */
    public function updateLineUserName(Request $request, $leadId)
    {
        $lead = CustomerLead::findOrFail($leadId);
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'editable_name' => 'required|string|max:100'
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $editableName = $request->get('editable_name');
        
        // 如果是LINE user_id格式，更新LineUser表中的業務顯示名稱
        if ($lead->line_id && $this->isLineUserId($lead->line_id)) {
            $lineUser = LineUser::where('line_user_id', $lead->line_id)->first();
            if ($lineUser) {
                $oldName = $lineUser->getDisplayName();
                $oldApiName = $lineUser->getApiDisplayName();
                
                // Point 39: 使用業務名稱更新方法，保護API原始資料
                $lineUser->updateBusinessDisplayName($editableName, $user ? $user->id : null);
                
                // 記錄名稱變更
                \Log::info('Point 39 - LINE用戶業務名稱變更', [
                    'lead_id' => $leadId,
                    'line_user_id' => $lead->line_id,
                    'line_user_table_id' => $lineUser->id,
                    'old_business_name' => $oldName,
                    'new_business_name' => $editableName,
                    'api_name_preserved' => $oldApiName,
                    'updated_by' => $user ? $user->id : null,
                    'updated_by_name' => $user ? $user->name : 'Unknown',
                    'updated_at' => now(),
                    'note' => 'API原始名稱已保護，不受業務修改影響'
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'LINE用戶名稱更新成功',
                    'old_name' => $oldName,
                    'new_name' => $editableName
                ]);
            }
        }
        
        return response()->json([
            'success' => false,
            'message' => '無法更新：此不是有效的LINE用戶'
        ], 400);
    }
}
