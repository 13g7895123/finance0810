<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebhookExecutionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookLogController extends BaseApiController
{
    /**
     * Get paginated webhook execution logs
     */
    public function index(Request $request)
    {
        try {
            // Check if table exists before querying
            if (!$this->tableExists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Webhook logs table not found. Please run database migrations.',
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 20,
                        'total' => 0,
                        'last_page' => 1,
                        'has_more_pages' => false
                    ],
                    'setup_required' => true
                ], 404);
            }

            $query = WebhookExecutionLog::query()
                ->orderBy('started_at', 'desc');

            // Filter by webhook type
            if ($request->filled('type')) {
                $query->byWebhookType($request->input('type'));
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->byStatus($request->input('status'));
            }

            // Filter by date range
            if ($request->filled('date_from')) {
                $query->where('started_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->where('started_at', '<=', $request->input('date_to'));
            }

            // Filter by recent days (default: 7 days)
            if (!$request->filled('date_from') && !$request->filled('date_to')) {
                $days = $request->input('days', 7);
                $query->recent($days);
            }

            // Search by execution ID or IP address
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('execution_id', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%");
                });
            }

            $perPage = min($request->input('per_page', 20), 100);
            $logs = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                    'has_more_pages' => $logs->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve webhook logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific webhook execution log by ID
     */
    public function show($id)
    {
        try {
            $log = WebhookExecutionLog::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $log
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook log not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get webhook execution log by execution ID
     */
    public function getByExecutionId($executionId)
    {
        try {
            $log = WebhookExecutionLog::where('execution_id', $executionId)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $log
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook log not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get webhook execution statistics
     */
    public function statistics(Request $request)
    {
        try {
            $days = $request->input('days', 7);
            $webhookType = $request->input('type', 'line');

            $query = WebhookExecutionLog::query()
                ->recent($days)
                ->byWebhookType($webhookType);

            $stats = [
                'total_executions' => $query->count(),
                'successful' => $query->clone()->byStatus('completed')->count(),
                'failed' => $query->clone()->byStatus('failed')->count(),
                'in_progress' => $query->clone()->whereIn('status', ['started', 'processing'])->count(),
                'average_duration_ms' => $query->clone()
                    ->whereNotNull('duration_ms')
                    ->avg('duration_ms'),
                'status_breakdown' => $query->clone()
                    ->select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->get()
                    ->mapWithKeys(fn($item) => [$item->status => $item->count]),
                'daily_stats' => $query->clone()
                    ->select(
                        DB::raw('DATE(started_at) as date'),
                        DB::raw('count(*) as total'),
                        DB::raw('count(case when status = "completed" then 1 end) as successful'),
                        DB::raw('count(case when status = "failed" then 1 end) as failed')
                    )
                    ->groupBy(DB::raw('DATE(started_at)'))
                    ->orderBy('date', 'desc')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => [
                    'days' => $days,
                    'webhook_type' => $webhookType,
                    'from' => now()->subDays($days)->format('Y-m-d'),
                    'to' => now()->format('Y-m-d')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete old webhook logs (cleanup)
     */
    public function cleanup(Request $request)
    {
        try {
            $daysToKeep = $request->input('keep_days', 30);
            $cutoffDate = now()->subDays($daysToKeep);

            $deletedCount = WebhookExecutionLog::where('started_at', '<', $cutoffDate)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Webhook logs cleaned up successfully',
                'deleted_count' => $deletedCount,
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup webhook logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent webhook logs for live monitoring
     */
    public function recent(Request $request)
    {
        try {
            $minutes = $request->input('minutes', 60);
            $limit = min($request->input('limit', 50), 100);

            $logs = WebhookExecutionLog::query()
                ->where('started_at', '>=', now()->subMinutes($minutes))
                ->orderBy('started_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $logs,
                'period' => [
                    'minutes' => $minutes,
                    'from' => now()->subMinutes($minutes)->format('Y-m-d H:i:s'),
                    'to' => now()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recent logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export webhook logs to CSV
     */
    public function export(Request $request)
    {
        try {
            $query = WebhookExecutionLog::query()
                ->orderBy('started_at', 'desc');

            // Apply same filters as index method
            if ($request->filled('type')) {
                $query->byWebhookType($request->input('type'));
            }

            if ($request->filled('status')) {
                $query->byStatus($request->input('status'));
            }

            if ($request->filled('date_from')) {
                $query->where('started_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->where('started_at', '<=', $request->input('date_to'));
            }

            if (!$request->filled('date_from') && !$request->filled('date_to')) {
                $days = $request->input('days', 7);
                $query->recent($days);
            }

            $logs = $query->limit(5000)->get(); // Limit export to 5000 records

            $csvContent = "Execution ID,Webhook Type,Status,Started At,Completed At,Duration (ms),IP Address,Events Count,Error Message\n";

            foreach ($logs as $log) {
                $csvContent .= sprintf(
                    '"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                    $log->execution_id,
                    $log->webhook_type,
                    $log->status,
                    $log->started_at,
                    $log->completed_at ?? '',
                    $log->duration_ms ?? '',
                    $log->ip_address,
                    $log->events_count,
                    str_replace('"', '""', $log->error_message ?? '')
                );
            }

            return response($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="webhook_logs_' . date('Y-m-d_H-i-s') . '.csv"'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export webhook logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if webhook execution logs table exists
     */
    private function tableExists(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('webhook_execution_logs');
        } catch (\Exception $e) {
            return false;
        }
    }
}