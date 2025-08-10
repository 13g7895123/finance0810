<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;

class CustomerOwnershipMiddleware
{
    /**
     * Handle an incoming request for customer-related operations.
     * Staff members can only access customers assigned to them.
     * Managers and admins can access all customers.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // Admin and managers can access all customers
        if ($user->hasRole(['admin', 'executive', 'manager'])) {
            return $next($request);
        }

        // For staff members, check customer ownership
        if ($user->hasRole('staff')) {
            $customerId = $request->route('customer') 
                ? $request->route('customer')->id ?? $request->route('customer')
                : null;

            if ($customerId) {
                $customer = Customer::find($customerId);
                
                if (!$customer || $customer->assigned_to !== $user->id) {
                    return response()->json([
                        'error' => 'Forbidden',
                        'message' => '您只能存取分配給您的客戶資料'
                    ], 403);
                }
            }

            // For listing customers, filter by assigned_to in the controller
            return $next($request);
        }

        return response()->json([
            'error' => 'Forbidden',
            'message' => '您沒有權限執行此操作'
        ], 403);
    }
}