<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;

use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('customer.ownership')->except([
            'trackingList',
            'submittable',
            'getSalesUsers'
        ]);
    }

    /**
     * Display a listing of customers.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Customer::with(['assignedUser', 'creator']);

        // Staff can only see their assigned customers
        if ($user->isStaff()) {
            $query->where('assigned_to', $user->id);
        }

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Source filters
        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->has('website_source')) {
            $query->where('website_source', $request->website_source);
        }

        // Blacklist filters
        if ($request->boolean('is_blacklisted', null) !== null) {
            $query->where('is_blacklisted', $request->boolean('is_blacklisted'));
        }
        if ($request->has('blacklist_status')) {
            $query->where('blacklist_status', $request->blacklist_status);
        }
        if ($request->boolean('is_hidden', null) !== null) {
            $query->where('is_hidden', $request->boolean('is_hidden'));
        }


        if ($request->has('region')) {
            $query->where('region', $request->region);
        }

        if ($request->has('assigned_to')) {
            $assignedTo = $request->assigned_to;
            if ($assignedTo === 'null' || $assignedTo === null) {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $assignedTo);
            }
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting: newest case first, fallback to created_at
        $perPage = $request->get('per_page', 15);
        $customers = $query
            ->orderByRaw('CASE WHEN latest_case_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('latest_case_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($customers);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:100',
            'region' => 'nullable|string|max:50',
            'website_source' => 'nullable|string|max:100',
            'channel' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        
        // Staff can only create customers assigned to themselves
        $assignedTo = $request->assigned_to;
        if ($user->isStaff()) {
            $assignedTo = $user->id;
        }

        $customer = Customer::create(array_merge($validator->validated(), [
            'created_by' => $user->id,
            'assigned_to' => $assignedTo ?? $user->id,
            'status' => Customer::STATUS_NEW,
            'tracking_status' => Customer::TRACKING_PENDING,
        ]));

        // Log activity
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'activity_type' => CustomerActivity::TYPE_CREATED,
            'description' => "客戶資料已建立",
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'message' => '客戶資料已建立',
            'customer' => $customer->load(['assignedUser', 'creator'])
        ], 201);
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer)
    {
        return response()->json([
            'customer' => $customer->load([
                'assignedUser', 
                'creator', 
                'cases', 
                'bankRecords', 
                'activities.user'
            ])
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email|max:100',
            'region' => 'nullable|string|max:50',
            'website_source' => 'nullable|string|max:100',
            'channel' => 'nullable|string|max:50',
            'status' => 'sometimes|in:' . implode(',', array_keys(Customer::getStatusOptions())),
            'tracking_status' => 'sometimes|in:' . implode(',', array_keys(Customer::getTrackingStatusOptions())),
            'customer_level' => 'sometimes|in:A,B,C',
            'notes' => 'nullable|string|max:1000',
            'assigned_to' => 'nullable|exists:users,id',
            'next_contact_date' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $oldData = $customer->toArray();

        // Staff cannot reassign customers or change certain fields
        if ($user->isStaff()) {
            $validated = collect($validator->validated());
            $validated->forget(['assigned_to', 'approved_amount', 'disbursed_amount']);
            $customer->update($validated->toArray());
        } else {
            $customer->update($validator->validated());
        }

        // Log activity
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'activity_type' => CustomerActivity::TYPE_UPDATED,
            'description' => "客戶資料已更新",
            'old_data' => $oldData,
            'new_data' => $customer->toArray(),
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'message' => '客戶資料已更新',
            'customer' => $customer->load(['assignedUser', 'creator'])
        ]);
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer)
    {
        // Point 34: Add debugging logs for delete functionality
        \Log::info('Point 34 - Customer delete method called', [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'request_method' => request()->method(),
            'request_path' => request()->path()
        ]);

        $user = Auth::user();

        \Log::info('Point 34 - User attempting delete', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'is_manager' => $user->isManager(),
            'user_roles' => $user->getRoleNames()->toArray()
        ]);

        // Only managers and admins can delete customers
        if (!$user->isManager()) {
            \Log::warning('Point 34 - Delete rejected: User is not manager', [
                'user_id' => $user->id,
                'customer_id' => $customer->id
            ]);
            return response()->json(['error' => '您沒有權限刪除客戶資料'], 403);
        }

        // Log activity before deletion
        try {
            \Log::info('Point 34 - Creating customer activity record', [
                'customer_id' => $customer->id,
                'user_id' => $user->id
            ]);

            CustomerActivity::create([
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'activity_type' => 'deleted',
                'description' => "客戶資料已刪除",
                'old_data' => $customer->toArray(),
                'ip_address' => request()->ip(),
            ]);

            \Log::info('Point 34 - Deleting customer record', [
                'customer_id' => $customer->id
            ]);

            $customer->delete();

            \Log::info('Point 34 - Customer delete completed successfully', [
                'customer_id' => $customer->id,
                'user_id' => $user->id
            ]);

            return response()->json(['message' => '客戶資料已刪除']);

        } catch (\Exception $e) {
            \Log::error('Point 34 - Customer delete failed', [
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => '刪除客戶時發生錯誤',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign customer to user.
     */
    public function assignToUser(Request $request, Customer $customer)
    {
        $user = Auth::user();

        if (!$user->isManager()) {
            return response()->json(['error' => '您沒有權限分配客戶'], 403);
        }

        $validator = Validator::make($request->all(), [
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldAssignee = $customer->assignedUser;
        $newAssignedTo = $request->assigned_to;
        
        $customer->update(['assigned_to' => $newAssignedTo]);

        // Log activity
        if ($newAssignedTo) {
            $newAssignee = User::find($newAssignedTo);
            $description = $oldAssignee 
                ? "客戶已從 {$oldAssignee->name} 重新分配給 {$newAssignee->name}"
                : "客戶已分配給 {$newAssignee->name}";
        } else {
            $description = $oldAssignee 
                ? "客戶已從 {$oldAssignee->name} 取消分配"
                : "客戶分配已取消";
        }
        
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'activity_type' => CustomerActivity::TYPE_ASSIGNED,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'message' => $newAssignedTo ? '客戶分配成功' : '客戶分配已取消',
            'customer' => $customer->load(['assignedUser', 'creator'])
        ]);
    }

    /**
     * Set tracking date for customer.
     */
    public function setTrackDate(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'tracking_date' => 'required|date|after:today',
            'tracking_status' => 'sometimes|in:' . implode(',', array_keys(Customer::getTrackingStatusOptions())),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer->update([
            'tracking_date' => $request->tracking_date,
            'tracking_status' => $request->tracking_status ?? Customer::TRACKING_SCHEDULED,
            'next_contact_date' => $request->tracking_date,
        ]);

        // Log activity
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'activity_type' => 'track_scheduled',
            'description' => "設定追蹤日期: {$request->tracking_date}",
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'message' => '追蹤日期已設定',
            'customer' => $customer
        ]);
    }

    /**
     * Update customer status.
     */
    public function submittable(Request $request)
    {
        $user = Auth::user();
        $query = Customer::query()
            ->whereNull('case_status')
            ->whereIn('status', [Customer::STATUS_INTERESTED, Customer::STATUS_CONTACTED]);

        // Staff 僅能看到自己的客戶
        if ($user->isStaff()) {
            $query->where('assigned_to', $user->id);
        }

        if ($request->has('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%$s%")
                  ->orWhere('phone', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%");
            });
        }

        $perPage = (int)($request->get('per_page', 15));
        $customers = $query->orderByDesc('created_at')->paginate($perPage);
        return response()->json($customers);
    }

    public function updateStatus(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:' . implode(',', array_keys(Customer::getStatusOptions())),
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldStatus = $customer->status;
        $customer->update([
            'status' => $request->status,
            'notes' => $request->notes ? $customer->notes . "\n" . $request->notes : $customer->notes,
        ]);

        // Log activity
        $statusLabels = Customer::getStatusOptions();
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'activity_type' => CustomerActivity::TYPE_STATUS_CHANGED,
            'description' => "狀態變更: {$statusLabels[$oldStatus]} → {$statusLabels[$request->status]}",
            'old_data' => ['status' => $oldStatus],
            'new_data' => ['status' => $request->status],
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'message' => '客戶狀態已更新',
            'customer' => $customer
        ]);
    }

    /**
     * Get customer history.
     */
    public function getHistory(Customer $customer)
    {
        $activities = $customer->activities()->with('user')->paginate(20);

        return response()->json($activities);
    }

    /**
     * Link customer with LINE user ID
     */
    public function linkLineUser(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'line_user_id' => 'required|string|max:100',
            'line_display_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if LINE user ID is already linked to another customer
        $existingCustomer = Customer::where('line_user_id', $request->line_user_id)
            ->where('id', '!=', $customer->id)
            ->first();

        if ($existingCustomer) {
            return response()->json([
                'error' => 'LINE 用戶已綁定到其他客戶',
                'existing_customer' => $existingCustomer->name
            ], 409);
        }

        $oldLineUserId = $customer->line_user_id;
        $customer->update([
            'line_user_id' => $request->line_user_id,
            'line_display_name' => $request->line_display_name,
        ]);

        // Log activity
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'activity_type' => 'line_linked',
            'description' => $oldLineUserId 
                ? "LINE 用戶更新: {$request->line_display_name} ({$request->line_user_id})"
                : "LINE 用戶綁定: {$request->line_display_name} ({$request->line_user_id})",
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'message' => 'LINE 用戶綁定成功',
            'customer' => $customer->load(['assignedUser', 'creator'])
        ]);
    }

    /**
     * Unlink customer from LINE user
     */
    public function unlinkLineUser(Customer $customer)
    {
        $oldLineUserId = $customer->line_user_id;
        $oldDisplayName = $customer->line_display_name;

        if (!$oldLineUserId) {
            return response()->json(['error' => '客戶未綁定 LINE 用戶'], 400);
        }

        $customer->update([
            'line_user_id' => null,
            'line_display_name' => null,
        ]);

        // Log activity
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => Auth::id(),
            'activity_type' => 'line_unlinked',
            'description' => "LINE 用戶解除綁定: {$oldDisplayName} ({$oldLineUserId})",
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'message' => 'LINE 用戶解除綁定成功',
            'customer' => $customer->load(['assignedUser', 'creator'])
        ]);
    }

    /**
     * Check LINE friend status
     */
    public function checkLineFriendStatus(Customer $customer)
    {
        if (!$customer->line_user_id) {
            return response()->json([
                'is_friend' => false,
                'message' => '客戶未綁定 LINE 用戶'
            ]);
        }

        try {
            // Get LINE settings from database only
            $settings = \App\Models\LineIntegrationSetting::getAllSettings(true);
            $token = $settings['channel_access_token'] ?? '';

            if (!$token) {
                return response()->json([
                    'is_friend' => null,
                    'message' => 'LINE 整合未設定'
                ]);
            }

            // Check friend status via LINE API
            $client = new \GuzzleHttp\Client();
            $response = $client->get("https://api.line.me/v2/bot/profile/{$customer->line_user_id}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'timeout' => 10,
            ]);

            $profileData = json_decode($response->getBody()->getContents(), true);

            // Update display name if different
            if ($profileData['displayName'] !== $customer->line_display_name) {
                $customer->update(['line_display_name' => $profileData['displayName']]);
            }

            return response()->json([
                'is_friend' => true,
                'profile' => $profileData,
                'message' => '已建立好友關係'
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            // 404 means user is not a friend or LINE user ID is invalid
            if ($e->getResponse()->getStatusCode() === 404) {
                return response()->json([
                    'is_friend' => false,
                    'message' => '未建立好友關係或 LINE 用戶 ID 無效'
                ]);
            }

            return response()->json([
                'is_friend' => null,
                'message' => 'LINE API 錯誤: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'is_friend' => null,
                'message' => '檢查好友狀態時發生錯誤: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customers for tracking management (excludes invalid customers and blacklisted customers)
     */
    public function trackingList(Request $request)
    {
        $user = Auth::user();
        $query = Customer::with(['assignedUser', 'creator'])
            ->forTrackingManagement(); // Use the scope we created in the model

        // Staff can only see their assigned customers
        if ($user->isStaff()) {
            $query->where('assigned_to', $user->id);
        }

        // Apply filters
        if ($request->has('customer_level')) {
            $query->byCustomerLevel($request->customer_level);
        }

        if ($request->has('region')) {
            $query->where('region', $request->region);
        }

        if ($request->has('assigned_to')) {
            $assignedTo = $request->assigned_to;
            if ($assignedTo === 'null' || $assignedTo === null) {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', $assignedTo);
            }
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting: newest case first, fallback to created_at
        $perPage = $request->get('per_page', 15);
        $customers = $query
            ->orderByRaw('CASE WHEN latest_case_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByDesc('latest_case_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($customers);
    }

    /**
     * Update customer level
     */
    public function updateCustomerLevel(Request $request, Customer $customer)
    {
        $validator = Validator::make($request->all(), [
            'customer_level' => 'required|in:A,B,C',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $oldLevel = $customer->customer_level;
        $newLevel = $request->customer_level;

        // Update customer level
        $customer->update([
            'customer_level' => $newLevel
        ]);

        // Log activity
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'activity_type' => CustomerActivity::TYPE_UPDATED,
            'description' => "客戶等級從 {$oldLevel} 變更為 {$newLevel}",
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'message' => '客戶等級已更新',
            'customer' => $customer->fresh(['assignedUser', 'creator'])
        ]);
    }

    /**
     * Get sales users for tracking management (accessible by all authenticated users)
     */
    public function getSalesUsers()
    {
        try {
            $salesUsers = User::whereHas('roles', function($query) {
                $query->where('name', 'staff');
            })
            ->select(['id', 'name', 'username', 'email'])
            ->get();

            return response()->json([
                'data' => $salesUsers,
                'message' => '業務人員列表獲取成功'
            ]);
        } catch (\Exception $e) {
            \Log::error('獲取業務人員列表失敗:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => '獲取業務人員列表失敗',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}