<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\CustomerCase;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:admin|manager');
    }

    /**
     * Generate daily report.
     */
    public function dailyReport(Request $request)
    {
        $date = $request->get('date', now()->format('Y-m-d'));
        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        $report = [
            'date' => $date,
            'customers' => [
                'total' => Customer::whereBetween('created_at', [$startDate, $endDate])->count(),
                'by_status' => Customer::whereBetween('created_at', [$startDate, $endDate])
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->get()
                    ->pluck('count', 'status'),
                'by_region' => Customer::whereBetween('created_at', [$startDate, $endDate])
                    ->select('region', DB::raw('count(*) as count'))
                    ->whereNotNull('region')
                    ->groupBy('region')
                    ->get()
                    ->pluck('count', 'region'),
                'by_website' => Customer::whereBetween('created_at', [$startDate, $endDate])
                    ->select('website_source', DB::raw('count(*) as count'))
                    ->whereNotNull('website_source')
                    ->groupBy('website_source')
                    ->get()
                    ->pluck('count', 'website_source'),
            ],
            'cases' => [
                'submitted' => CustomerCase::whereBetween('created_at', [$startDate, $endDate])->count(),
                'approved' => CustomerCase::whereBetween('approved_at', [$startDate, $endDate])->count(),
                'disbursed' => CustomerCase::whereBetween('disbursed_at', [$startDate, $endDate])->count(),
                'total_loan_amount' => CustomerCase::whereBetween('created_at', [$startDate, $endDate])->sum('loan_amount') ?: 0,
                'total_disbursed_amount' => CustomerCase::whereBetween('disbursed_at', [$startDate, $endDate])->sum('disbursed_amount') ?: 0,
            ],
        ];

        return response()->json($report);
    }

    /**
     * Generate monthly report.
     */
    public function monthlyReport(Request $request)
    {
        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);
        
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $report = [
            'year' => $year,
            'month' => $month,
            'period' => $startDate->format('Y年m月'),
            'customers' => [
                'total' => Customer::whereBetween('created_at', [$startDate, $endDate])->count(),
                'converted' => Customer::whereBetween('created_at', [$startDate, $endDate])
                    ->where('status', Customer::STATUS_CONVERTED)->count(),
                'by_assignee' => Customer::whereBetween('created_at', [$startDate, $endDate])
                    ->join('users', 'customers.assigned_to', '=', 'users.id')
                    ->select('users.name', DB::raw('count(*) as count'))
                    ->groupBy('users.id', 'users.name')
                    ->get()
                    ->pluck('count', 'name'),
            ],
            'cases' => [
                'total' => CustomerCase::whereBetween('created_at', [$startDate, $endDate])->count(),
                'approved' => CustomerCase::whereBetween('approved_at', [$startDate, $endDate])->count(),
                'rejected' => CustomerCase::whereBetween('rejected_at', [$startDate, $endDate])->count(),
                'disbursed' => CustomerCase::whereBetween('disbursed_at', [$startDate, $endDate])->count(),
                'total_amount' => CustomerCase::whereBetween('created_at', [$startDate, $endDate])->sum('loan_amount') ?: 0,
                'approved_amount' => CustomerCase::whereBetween('approved_at', [$startDate, $endDate])->sum('approved_amount') ?: 0,
                'disbursed_amount' => CustomerCase::whereBetween('disbursed_at', [$startDate, $endDate])->sum('disbursed_amount') ?: 0,
            ],
        ];

        // Calculate rates
        $report['conversion_rate'] = $report['customers']['total'] > 0 
            ? round(($report['customers']['converted'] / $report['customers']['total']) * 100, 2) 
            : 0;

        $report['approval_rate'] = $report['cases']['total'] > 0 
            ? round(($report['cases']['approved'] / $report['cases']['total']) * 100, 2) 
            : 0;

        return response()->json($report);
    }

    /**
     * Generate website performance report.
     */
    public function websiteReport(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        $startDate = Carbon::parse($dateFrom)->startOfDay();
        $endDate = Carbon::parse($dateTo)->endOfDay();

        $websiteData = Customer::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'website_source',
                DB::raw('count(*) as total_leads'),
                DB::raw('sum(case when status = "converted" then 1 else 0 end) as converted_leads'),
                DB::raw('count(case when case_status = "submitted" then 1 end) as submitted_cases'),
                DB::raw('count(case when case_status = "approved" then 1 end) as approved_cases'),
                DB::raw('sum(approved_amount) as total_approved_amount'),
                DB::raw('sum(disbursed_amount) as total_disbursed_amount')
            )
            ->whereNotNull('website_source')
            ->groupBy('website_source')
            ->orderBy('total_leads', 'desc')
            ->get();

        $report = $websiteData->map(function($item) {
            return [
                'website' => $item->website_source,
                'total_leads' => $item->total_leads,
                'converted_leads' => $item->converted_leads,
                'conversion_rate' => $item->total_leads > 0 
                    ? round(($item->converted_leads / $item->total_leads) * 100, 2) 
                    : 0,
                'submitted_cases' => $item->submitted_cases,
                'approved_cases' => $item->approved_cases,
                'approval_rate' => $item->submitted_cases > 0 
                    ? round(($item->approved_cases / $item->submitted_cases) * 100, 2) 
                    : 0,
                'total_approved_amount' => $item->total_approved_amount ?: 0,
                'total_disbursed_amount' => $item->total_disbursed_amount ?: 0,
            ];
        });

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'websites' => $report,
            'summary' => [
                'total_websites' => $websiteData->count(),
                'total_leads' => $websiteData->sum('total_leads'),
                'total_converted' => $websiteData->sum('converted_leads'),
                'average_conversion_rate' => $websiteData->count() > 0 
                    ? round($report->avg('conversion_rate'), 2) 
                    : 0,
            ]
        ]);
    }

    /**
     * Generate region performance report.
     */
    public function regionReport(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        $startDate = Carbon::parse($dateFrom)->startOfDay();
        $endDate = Carbon::parse($dateTo)->endOfDay();

        $regionData = Customer::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'region',
                DB::raw('count(*) as total_customers'),
                DB::raw('sum(case when status = "converted" then 1 else 0 end) as converted_customers'),
                DB::raw('sum(approved_amount) as total_approved_amount'),
                DB::raw('sum(disbursed_amount) as total_disbursed_amount')
            )
            ->whereNotNull('region')
            ->groupBy('region')
            ->orderBy('total_customers', 'desc')
            ->get();

        $report = $regionData->map(function($item) {
            return [
                'region' => $item->region,
                'total_customers' => $item->total_customers,
                'converted_customers' => $item->converted_customers,
                'conversion_rate' => $item->total_customers > 0 
                    ? round(($item->converted_customers / $item->total_customers) * 100, 2) 
                    : 0,
                'total_approved_amount' => $item->total_approved_amount ?: 0,
                'total_disbursed_amount' => $item->total_disbursed_amount ?: 0,
                'average_amount_per_customer' => $item->converted_customers > 0 
                    ? round($item->total_approved_amount / $item->converted_customers, 2) 
                    : 0,
            ];
        });

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'regions' => $report,
            'summary' => [
                'total_regions' => $regionData->count(),
                'total_customers' => $regionData->sum('total_customers'),
                'total_converted' => $regionData->sum('converted_customers'),
                'total_approved_amount' => $regionData->sum('total_approved_amount'),
                'average_conversion_rate' => $regionData->count() > 0 
                    ? round($report->avg('conversion_rate'), 2) 
                    : 0,
            ]
        ]);
    }

    /**
     * Generate approval rate report.
     */
    public function approvalRate(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        $startDate = Carbon::parse($dateFrom)->startOfDay();
        $endDate = Carbon::parse($dateTo)->endOfDay();

        // Daily approval rates
        $dailyRates = CustomerCase::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as total_cases'),
                DB::raw('sum(case when status = "approved" then 1 else 0 end) as approved_cases'),
                DB::raw('sum(case when status = "rejected" then 1 else 0 end) as rejected_cases')
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'total_cases' => $item->total_cases,
                    'approved_cases' => $item->approved_cases,
                    'rejected_cases' => $item->rejected_cases,
                    'approval_rate' => $item->total_cases > 0 
                        ? round(($item->approved_cases / $item->total_cases) * 100, 2) 
                        : 0,
                ];
            });

        // Overall statistics
        $totalCases = CustomerCase::whereBetween('created_at', [$startDate, $endDate])->count();
        $approvedCases = CustomerCase::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'approved')->count();
        $rejectedCases = CustomerCase::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'rejected')->count();

        return response()->json([
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'daily_rates' => $dailyRates,
            'summary' => [
                'total_cases' => $totalCases,
                'approved_cases' => $approvedCases,
                'rejected_cases' => $rejectedCases,
                'pending_cases' => $totalCases - $approvedCases - $rejectedCases,
                'overall_approval_rate' => $totalCases > 0 
                    ? round(($approvedCases / $totalCases) * 100, 2) 
                    : 0,
                'rejection_rate' => $totalCases > 0 
                    ? round(($rejectedCases / $totalCases) * 100, 2) 
                    : 0,
            ]
        ]);
    }
}