<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('customer.ownership');
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

        if ($request->has('region')) {
            $query->where('region', $request->region);
        }

        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
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

        // Pagination
        $perPage = $request->get('per_page', 15);
        $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

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
        $user = Auth::user();

        // Only managers and admins can delete customers
        if (!$user->isManager()) {
            return response()->json(['error' => '您沒有權限刪除客戶資料'], 403);
        }

        // Log activity before deletion
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'activity_type' => 'deleted',
            'description' => "客戶資料已刪除",
            'old_data' => $customer->toArray(),
            'ip_address' => request()->ip(),
        ]);

        $customer->delete();

        return response()->json(['message' => '客戶資料已刪除']);
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
            'assigned_to' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $oldAssignee = $customer->assignedUser;
        $customer->update(['assigned_to' => $request->assigned_to]);

        // Log activity
        $newAssignee = User::find($request->assigned_to);
        CustomerActivity::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'activity_type' => CustomerActivity::TYPE_ASSIGNED,
            'description' => "客戶已從 {$oldAssignee?->name} 重新分配給 {$newAssignee->name}",
            'ip_address' => request()->ip(),
        ]);

        return response()->json([
            'message' => '客戶分配成功',
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
}