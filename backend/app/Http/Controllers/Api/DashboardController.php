<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\CustomerCase;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Get dashboard statistics.
     */
    public function getStats(Request $request)
    {
        $user = Auth::user();
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Base query for customers
        $customerQuery = Customer::whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay(),
        ]);

        // Filter by user role
        if ($user->isStaff()) {
            $customerQuery->where('assigned_to', $user->id);
        }

        $stats = [
            'total_customers' => (clone $customerQuery)->count(),
            'new_customers' => (clone $customerQuery)->where('status', Customer::STATUS_NEW)->count(),
            'contacted_customers' => (clone $customerQuery)->where('status', Customer::STATUS_CONTACTED)->count(),
            'converted_customers' => (clone $customerQuery)->where('status', Customer::STATUS_CONVERTED)->count(),
            'pending_follow_ups' => Customer::when($user->isStaff(), function($q) use ($user) {
                    return $q->where('assigned_to', $user->id);
                })
                ->where('next_contact_date', '<=', now())
                ->whereNotIn('status', [Customer::STATUS_CONVERTED, Customer::STATUS_INVALID])
                ->count(),
        ];

        // Conversion rate
        $stats['conversion_rate'] = $stats['total_customers'] > 0 
            ? round(($stats['converted_customers'] / $stats['total_customers']) * 100, 2) 
            : 0;

        // Additional stats for managers and admins
        if ($user->isManager()) {
            $caseQuery = CustomerCase::whereBetween('created_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay(),
            ]);

            $stats['total_cases'] = $caseQuery->count();
            $stats['approved_cases'] = (clone $caseQuery)->where('status', 'approved')->count();
            $stats['disbursed_cases'] = (clone $caseQuery)->where('status', 'disbursed')->count();
            $stats['total_loan_amount'] = (clone $caseQuery)->sum('loan_amount') ?: 0;
            $stats['total_disbursed_amount'] = (clone $caseQuery)->where('status', 'disbursed')->sum('disbursed_amount') ?: 0;

            // Approval rate
            $stats['approval_rate'] = $stats['total_cases'] > 0 
                ? round(($stats['approved_cases'] / $stats['total_cases']) * 100, 2) 
                : 0;
        }

        return response()->json($stats);
    }

    /**
     * Get recent customers.
     */
    public function getRecentCustomers(Request $request)
    {
        $user = Auth::user();
        $limit = $request->get('limit', 10);

        $query = Customer::with(['assignedUser', 'creator'])
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        // Staff can only see their customers
        if ($user->isStaff()) {
            $query->where('assigned_to', $user->id);
        }

        $customers = $query->get();

        return response()->json($customers);
    }

    /**
     * Get monthly summary data.
     */
    public function getMonthlySummary(Request $request)
    {
        $user = Auth::user();
        $months = $request->get('months', 6);

        $monthlyData = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $customerQuery = Customer::whereBetween('created_at', [$startOfMonth, $endOfMonth]);

            // Filter by user role
            if ($user->isStaff()) {
                $customerQuery->where('assigned_to', $user->id);
            }

            $monthData = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('Y年m月'),
                'total_customers' => (clone $customerQuery)->count(),
                'converted_customers' => (clone $customerQuery)->where('status', Customer::STATUS_CONVERTED)->count(),
            ];

            // Add case data for managers
            if ($user->isManager()) {
                $caseQuery = CustomerCase::whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                $monthData['total_cases'] = $caseQuery->count();
                $monthData['approved_cases'] = (clone $caseQuery)->where('status', 'approved')->count();
                $monthData['disbursed_amount'] = (clone $caseQuery)->where('status', 'disbursed')->sum('disbursed_amount') ?: 0;
            }

            $monthlyData[] = $monthData;
        }

        return response()->json($monthlyData);
    }

    /**
     * Get dashboard charts data.
     */
    public function getChartsData(Request $request)
    {
        $user = Auth::user();
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        // Customer status distribution
        $statusQuery = Customer::whereBetween('created_at', [
            Carbon::parse($dateFrom)->startOfDay(),
            Carbon::parse($dateTo)->endOfDay(),
        ]);

        if ($user->isStaff()) {
            $statusQuery->where('assigned_to', $user->id);
        }

        $statusData = $statusQuery->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function($item) {
                $statusLabels = Customer::getStatusOptions();
                return [$statusLabels[$item->status] ?? $item->status => $item->count];
            });

        $charts = [
            'status_distribution' => $statusData,
        ];

        // Region distribution for managers
        if ($user->isManager()) {
            $regionData = Customer::whereBetween('created_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay(),
            ])
            ->select('region', DB::raw('count(*) as count'))
            ->whereNotNull('region')
            ->groupBy('region')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->pluck('count', 'region');

            $charts['region_distribution'] = $regionData;
        }

        return response()->json($charts);
    }
}