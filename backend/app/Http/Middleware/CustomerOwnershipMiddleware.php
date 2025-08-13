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
        try {
            if (!Auth::check()) {
                \Log::info('CustomerOwnership middleware: User not authenticated');
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $user = Auth::user();
            \Log::info('CustomerOwnership middleware: User authenticated', ['user_id' => $user->id]);

            // Check if user has roles (this might fail if roles aren't properly set up)
            try {
                $userRoles = $user->getRoleNames();
                \Log::info('CustomerOwnership middleware: User roles', ['roles' => $userRoles->toArray()]);
            } catch (\Exception $e) {
                \Log::error('CustomerOwnership middleware: Failed to get user roles', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id
                ]);
                
                // If role checking fails, only allow for now (temporary)
                \Log::warning('CustomerOwnership middleware: Allowing request due to role check failure');
                return $next($request);
            }

            // Admin and managers can access all customers
            if ($user->hasRole(['admin', 'executive', 'manager'])) {
                \Log::info('CustomerOwnership middleware: User has management role, allowing access');
                return $next($request);
            }

            // For staff members, check customer ownership
            if ($user->hasRole('staff')) {
                \Log::info('CustomerOwnership middleware: User is staff, checking customer ownership');
                
                $customerId = $request->route('customer') 
                    ? $request->route('customer')->id ?? $request->route('customer')
                    : null;

                if ($customerId) {
                    $customer = Customer::find($customerId);
                    
                    if (!$customer || $customer->assigned_to !== $user->id) {
                        \Log::warning('CustomerOwnership middleware: Staff user denied access to customer', [
                            'user_id' => $user->id,
                            'customer_id' => $customerId,
                            'customer_assigned_to' => $customer ? $customer->assigned_to : null
                        ]);
                        
                        return response()->json([
                            'error' => 'Forbidden',
                            'message' => '您只能存取分配給您的客戶資料'
                        ], 403);
                    }
                }

                // For listing customers, filter by assigned_to in the controller
                \Log::info('CustomerOwnership middleware: Staff access granted');
                return $next($request);
            }

            \Log::warning('CustomerOwnership middleware: User has no recognized role', [
                'user_id' => $user->id,
                'roles' => $userRoles ?? 'unknown'
            ]);

            return response()->json([
                'error' => 'Forbidden',
                'message' => '您沒有權限執行此操作'
            ], 403);
            
        } catch (\Exception $e) {
            \Log::error('CustomerOwnership middleware: Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_path' => $request->path(),
                'request_method' => $request->method()
            ]);
            
            return response()->json([
                'error' => 'Server Error',
                'message' => 'Internal server error in customer ownership middleware'
            ], 500);
        }
    }
}