<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Services\LogAlertService;

class LogController extends Controller
{
    /**
     * 獲取錯誤日誌列表，提供詳細過濾和搜索功能
     */
    public function getErrorLogs(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'level' => 'nullable|string|in:emergency,alert,critical,error,warning,notice,info,debug',
                'search' => 'nullable|string|max:255',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'limit' => 'nullable|integer|min:1|max:1000',
                'include_trace' => 'nullable|boolean'
            ]);

            $logPath = storage_path('logs/laravel.log');

            if (!File::exists($logPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log file not found',
                    'data' => []
                ], 404);
            }

            $logs = $this->parseLogFile($logPath, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Error logs retrieved successfully',
                'data' => [
                    'logs' => $logs,
                    'total' => count($logs),
                    'filters_applied' => $this->getAppliedFilters($validated),
                    'summary' => $this->getLogsSummary($logs)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Log Controller Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '獲取日誌時發生錯誤',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * 獲取系統錯誤統計信息
     */
    public function getErrorStats(Request $request): JsonResponse
    {
        try {
            $days = $request->input('days', 7);
            $logPath = storage_path('logs/laravel.log');

            if (!File::exists($logPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log file not found'
                ], 404);
            }

            $stats = $this->generateErrorStats($logPath, $days);

            return response()->json([
                'success' => true,
                'message' => 'Error statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            \Log::error('Log Stats Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => '獲取錯誤統計時發生錯誤',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * 獲取最近的嚴重錯誤
     */
    public function getCriticalErrors(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 50);
            $logPath = storage_path('logs/laravel.log');

            if (!File::exists($logPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log file not found',
                    'data' => []
                ], 404);
            }

            $criticalLogs = $this->parseLogFile($logPath, [
                'level' => 'error,critical,emergency',
                'limit' => $limit,
                'include_trace' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Critical errors retrieved successfully',
                'data' => [
                    'errors' => $criticalLogs,
                    'total' => count($criticalLogs),
                    'analyzed_patterns' => $this->analyzeCriticalPatterns($criticalLogs)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Critical Errors Retrieval Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => '獲取嚴重錯誤時發生錯誤',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * 清理舊日誌（僅清理指定天數之前的日誌）
     */
    public function cleanOldLogs(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'days_to_keep' => 'required|integer|min:1|max:365'
            ]);

            $daysToKeep = $validated['days_to_keep'];
            $cutoffDate = Carbon::now()->subDays($daysToKeep);

            $logPath = storage_path('logs/laravel.log');

            if (!File::exists($logPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Log file not found'
                ], 404);
            }

            $result = $this->cleanLogFile($logPath, $cutoffDate);

            return response()->json([
                'success' => true,
                'message' => "成功清理 {$daysToKeep} 天前的日誌",
                'data' => $result
            ]);

        } catch (\Exception $e) {
            \Log::error('Log Cleanup Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => '清理日誌時發生錯誤',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * 解析日誌文件
     */
    private function parseLogFile(string $logPath, array $filters): array
    {
        $content = File::get($logPath);
        $lines = explode("\n", $content);
        $logs = [];
        $currentLog = null;

        $levelFilter = isset($filters['level']) ? explode(',', $filters['level']) : null;
        $searchTerm = $filters['search'] ?? null;
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : null;
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : null;
        $limit = $filters['limit'] ?? 500;
        $includeTrace = $filters['include_trace'] ?? false;

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            // 檢查是否是新的日誌條目（以日期時間開始）
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)/', $line, $matches)) {
                // 如果有當前日誌，先處理它
                if ($currentLog && $this->matchesFilters($currentLog, $levelFilter, $searchTerm, $startDate, $endDate)) {
                    $logs[] = $currentLog;
                    if (count($logs) >= $limit) break;
                }

                // 開始新的日誌條目
                $currentLog = [
                    'timestamp' => $matches[1],
                    'environment' => $matches[2],
                    'level' => strtoupper($matches[3]),
                    'message' => $matches[4],
                    'context' => null,
                    'exception' => null,
                    'trace' => null,
                    'additional_lines' => []
                ];

                // 嘗試解析 JSON 上下文
                if (strpos($matches[4], '{') !== false) {
                    $parts = explode(' {', $matches[4], 2);
                    if (count($parts) === 2) {
                        $currentLog['message'] = trim($parts[0]);
                        $jsonPart = '{' . $parts[1];

                        try {
                            $context = json_decode($jsonPart, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $currentLog['context'] = $context;

                                // 提取異常信息
                                if (isset($context['exception'])) {
                                    $currentLog['exception'] = $this->parseExceptionInfo($context['exception']);
                                }
                            }
                        } catch (\Exception $e) {
                            // JSON 解析失敗，保持原始格式
                        }
                    }
                }
            } else if ($currentLog && !empty(trim($line))) {
                // 這是當前日誌的續行
                $currentLog['additional_lines'][] = trim($line);

                // 如果包含堆棧跟踪並且需要
                if ($includeTrace && (strpos($line, 'Stack trace:') !== false || strpos($line, '#') === 0)) {
                    if (!$currentLog['trace']) {
                        $currentLog['trace'] = [];
                    }
                    $currentLog['trace'][] = trim($line);
                }
            }
        }

        // 處理最後一個日誌條目
        if ($currentLog && $this->matchesFilters($currentLog, $levelFilter, $searchTerm, $startDate, $endDate)) {
            $logs[] = $currentLog;
        }

        // 按時間倒序排列（最新的在前）
        usort($logs, function($a, $b) {
            return strcmp($b['timestamp'], $a['timestamp']);
        });

        return array_slice($logs, 0, $limit);
    }

    /**
     * 檢查日誌是否符合過濾條件
     */
    private function matchesFilters(array $log, ?array $levelFilter, ?string $searchTerm, ?Carbon $startDate, ?Carbon $endDate): bool
    {
        // 級別過濾
        if ($levelFilter && !in_array(strtolower($log['level']), array_map('strtolower', $levelFilter))) {
            return false;
        }

        // 搜索過濾
        if ($searchTerm) {
            $searchableText = $log['message'] . ' ' . json_encode($log['context'] ?? '');
            if (stripos($searchableText, $searchTerm) === false) {
                return false;
            }
        }

        // 日期過濾
        $logDate = Carbon::parse($log['timestamp']);
        if ($startDate && $logDate->lt($startDate)) {
            return false;
        }
        if ($endDate && $logDate->gt($endDate)) {
            return false;
        }

        return true;
    }

    /**
     * 解析異常信息
     */
    private function parseExceptionInfo(string $exceptionString): array
    {
        $info = [
            'type' => null,
            'message' => null,
            'file' => null,
            'line' => null,
            'code' => null
        ];

        // 嘗試提取異常類型和消息
        if (preg_match('/\[object\] \(([^(]+)\(code: ([^)]+)\): (.+?) at (.+):(\d+)\)/', $exceptionString, $matches)) {
            $info['type'] = $matches[1];
            $info['code'] = $matches[2];
            $info['message'] = $matches[3];
            $info['file'] = $matches[4];
            $info['line'] = (int)$matches[5];
        }

        return $info;
    }

    /**
     * 獲取已應用的過濾器
     */
    private function getAppliedFilters(array $filters): array
    {
        return array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * 獲取日誌摘要
     */
    private function getLogsSummary(array $logs): array
    {
        $summary = [
            'total_entries' => count($logs),
            'by_level' => [],
            'common_errors' => [],
            'time_range' => null
        ];

        if (empty($logs)) {
            return $summary;
        }

        // 按級別統計
        foreach ($logs as $log) {
            $level = strtolower($log['level']);
            $summary['by_level'][$level] = ($summary['by_level'][$level] ?? 0) + 1;
        }

        // 統計常見錯誤
        $errorCounts = [];
        foreach ($logs as $log) {
            if (in_array(strtolower($log['level']), ['error', 'critical', 'emergency'])) {
                $message = substr($log['message'], 0, 100) . '...';
                $errorCounts[$message] = ($errorCounts[$message] ?? 0) + 1;
            }
        }

        arsort($errorCounts);
        $summary['common_errors'] = array_slice($errorCounts, 0, 5, true);

        // 時間範圍
        $timestamps = array_column($logs, 'timestamp');
        if (!empty($timestamps)) {
            $summary['time_range'] = [
                'earliest' => min($timestamps),
                'latest' => max($timestamps)
            ];
        }

        return $summary;
    }

    /**
     * 生成錯誤統計
     */
    private function generateErrorStats(string $logPath, int $days): array
    {
        $stats = [
            'period_days' => $days,
            'daily_counts' => [],
            'error_types' => [],
            'top_errors' => [],
            'firebase_issues' => 0,
            'database_issues' => 0,
            'api_issues' => 0
        ];

        $cutoffDate = Carbon::now()->subDays($days);
        $logs = $this->parseLogFile($logPath, [
            'start_date' => $cutoffDate->toDateString(),
            'level' => 'error,critical,emergency',
            'limit' => 10000
        ]);

        // 按日統計
        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $stats['daily_counts'][$date] = 0;
        }

        foreach ($logs as $log) {
            $date = Carbon::parse($log['timestamp'])->toDateString();
            if (isset($stats['daily_counts'][$date])) {
                $stats['daily_counts'][$date]++;
            }

            // 按錯誤類型統計
            $level = strtolower($log['level']);
            $stats['error_types'][$level] = ($stats['error_types'][$level] ?? 0) + 1;

            // 特定問題統計
            $message = strtolower($log['message']);
            if (strpos($message, 'firebase') !== false) {
                $stats['firebase_issues']++;
            }
            if (strpos($message, 'database') !== false || strpos($message, 'sql') !== false) {
                $stats['database_issues']++;
            }
            if (strpos($message, 'api') !== false) {
                $stats['api_issues']++;
            }
        }

        return $stats;
    }

    /**
     * 分析嚴重錯誤模式
     */
    private function analyzeCriticalPatterns(array $logs): array
    {
        $patterns = [
            'database_connection_issues' => 0,
            'firebase_authentication_issues' => 0,
            'missing_tables' => [],
            'api_route_errors' => 0,
            'migration_errors' => 0
        ];

        foreach ($logs as $log) {
            $message = strtolower($log['message']);

            if (strpos($message, 'connection refused') !== false || strpos($message, 'database') !== false) {
                $patterns['database_connection_issues']++;
            }

            if (strpos($message, 'invalid_grant') !== false || strpos($message, 'firebase') !== false) {
                $patterns['firebase_authentication_issues']++;
            }

            if (preg_match("/table .+? doesn't exist/", $message, $matches)) {
                $tableName = $matches[0];
                $patterns['missing_tables'][] = $tableName;
            }

            if (strpos($message, 'route') !== false && strpos($message, 'not found') !== false) {
                $patterns['api_route_errors']++;
            }

            if (strpos($message, 'migration') !== false || strpos($message, 'alter table') !== false) {
                $patterns['migration_errors']++;
            }
        }

        $patterns['missing_tables'] = array_unique($patterns['missing_tables']);

        return $patterns;
    }

    /**
     * 清理日誌文件
     */
    private function cleanLogFile(string $logPath, Carbon $cutoffDate): array
    {
        $content = File::get($logPath);
        $lines = explode("\n", $content);
        $keptLines = [];
        $removedCount = 0;
        $keptCount = 0;

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                $keptLines[] = $line;
                continue;
            }

            // 檢查日誌行的日期
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $logDate = Carbon::parse($matches[1]);

                if ($logDate->gte($cutoffDate)) {
                    $keptLines[] = $line;
                    $keptCount++;
                } else {
                    $removedCount++;
                }
            } else {
                // 不是日誌行開頭，保留
                $keptLines[] = $line;
            }
        }

        // 寫回文件
        File::put($logPath, implode("\n", $keptLines));

        return [
            'removed_entries' => $removedCount,
            'kept_entries' => $keptCount,
            'cutoff_date' => $cutoffDate->toDateTimeString()
        ];
    }

    /**
     * 獲取錯誤模式分析
     */
    public function getPatternAnalysis(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'hours' => 'nullable|integer|min:1|max:168' // 最多7天
            ]);

            $hours = $validated['hours'] ?? 24;
            $alertService = new LogAlertService();
            $analysis = $alertService->getErrorPatternAnalysis($hours);

            return response()->json([
                'success' => true,
                'message' => '錯誤模式分析完成',
                'data' => $analysis
            ]);

        } catch (\Exception $e) {
            \Log::error('Pattern Analysis Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => '分析錯誤模式時發生錯誤',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * 檢查並發送緊急錯誤警報
     */
    public function checkCriticalAlerts(Request $request): JsonResponse
    {
        try {
            $alertService = new LogAlertService();
            $result = $alertService->checkAndSendAlerts();

            return response()->json([
                'success' => true,
                'message' => '緊急錯誤檢查完成',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            \Log::error('Critical Alert Check Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => '檢查緊急警報時發生錯誤',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * 獲取實時系統健康狀態
     */
    public function getSystemHealth(Request $request): JsonResponse
    {
        try {
            $logPath = storage_path('logs/laravel.log');

            if (!File::exists($logPath)) {
                return response()->json([
                    'success' => true,
                    'message' => '無法找到日誌文件',
                    'data' => [
                        'status' => 'no_logs',
                        'health_score' => 100,
                        'issues' => [],
                        'last_checked' => now()->toISOString()
                    ]
                ]);
            }

            // 分析最近1小時的錯誤
            $recentErrors = $this->parseLogFile($logPath, [
                'start_date' => now()->subHour()->toDateString(),
                'level' => 'error,critical,emergency',
                'limit' => 1000
            ]);

            $healthScore = $this->calculateHealthScore($recentErrors);
            $issues = $this->identifySystemIssues($recentErrors);

            return response()->json([
                'success' => true,
                'message' => '系統健康狀態檢查完成',
                'data' => [
                    'status' => $this->getHealthStatus($healthScore),
                    'health_score' => $healthScore,
                    'recent_errors_count' => count($recentErrors),
                    'issues' => $issues,
                    'last_checked' => now()->toISOString(),
                    'recommendations' => $this->getHealthRecommendations($healthScore, $issues)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('System Health Check Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => '檢查系統健康狀態時發生錯誤',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * 計算系統健康分數 (0-100)
     */
    private function calculateHealthScore(array $recentErrors): int
    {
        $errorCount = count($recentErrors);

        if ($errorCount === 0) {
            return 100;
        }

        // 根據錯誤數量和嚴重性計算分數
        $criticalCount = 0;
        $errorLevelCount = 0;

        foreach ($recentErrors as $error) {
            if (in_array(strtolower($error['level']), ['critical', 'emergency'])) {
                $criticalCount++;
            } elseif (strtolower($error['level']) === 'error') {
                $errorLevelCount++;
            }
        }

        // 嚴重錯誤權重更高
        $penalty = ($criticalCount * 10) + ($errorLevelCount * 5);
        $score = max(0, 100 - $penalty);

        return (int)$score;
    }

    /**
     * 獲取健康狀態字符串
     */
    private function getHealthStatus(int $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 50) return 'warning';
        if ($score >= 30) return 'poor';
        return 'critical';
    }

    /**
     * 識別系統問題
     */
    private function identifySystemIssues(array $recentErrors): array
    {
        $issues = [];

        foreach ($recentErrors as $error) {
            $message = strtolower($error['message']);

            if (strpos($message, 'database') !== false || strpos($message, 'sql') !== false) {
                $issues['database'] = ($issues['database'] ?? 0) + 1;
            }
            if (strpos($message, 'firebase') !== false) {
                $issues['firebase'] = ($issues['firebase'] ?? 0) + 1;
            }
            if (strpos($message, 'memory') !== false || strpos($message, 'timeout') !== false) {
                $issues['performance'] = ($issues['performance'] ?? 0) + 1;
            }
            if (strpos($message, 'unauthorized') !== false || strpos($message, 'unauthenticated') !== false) {
                $issues['security'] = ($issues['security'] ?? 0) + 1;
            }
        }

        return $issues;
    }

    /**
     * 獲取健康建議
     */
    private function getHealthRecommendations(int $score, array $issues): array
    {
        $recommendations = [];

        if ($score < 70) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'general',
                'message' => '系統健康分數偏低，建議立即檢查錯誤日誌並處理問題'
            ];
        }

        foreach ($issues as $category => $count) {
            if ($count > 5) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'category' => $category,
                    'message' => "檢測到 {$count} 個 {$category} 相關問題，建議優先處理"
                ];
            }
        }

        return $recommendations;
    }
}