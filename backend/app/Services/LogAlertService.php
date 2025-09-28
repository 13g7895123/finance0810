<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Carbon\Carbon;

class LogAlertService
{
    /**
     * Check for critical errors and send alerts if necessary
     */
    public function checkAndSendAlerts(): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            return ['status' => 'no_log_file', 'alerts_sent' => 0];
        }

        $recentCriticalErrors = $this->getRecentCriticalErrors($logPath);
        $alertsSent = 0;

        if (count($recentCriticalErrors) > 0) {
            $alertsSent = $this->sendCriticalErrorAlerts($recentCriticalErrors);
        }

        return [
            'status' => 'completed',
            'critical_errors_found' => count($recentCriticalErrors),
            'alerts_sent' => $alertsSent,
            'checked_at' => now()->toISOString()
        ];
    }

    /**
     * Get recent critical errors from log file
     */
    private function getRecentCriticalErrors(string $logPath): array
    {
        $content = file_get_contents($logPath);
        $lines = explode("\n", $content);
        $criticalErrors = [];
        $cutoffTime = Carbon::now()->subMinutes(30); // 最近30分鐘

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            // 檢查是否是嚴重錯誤日誌行
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|EMERGENCY): (.+)/', $line, $matches)) {
                $timestamp = Carbon::parse($matches[1]);

                if ($timestamp->gte($cutoffTime)) {
                    $criticalErrors[] = [
                        'timestamp' => $matches[1],
                        'level' => $matches[2],
                        'message' => $matches[3],
                        'raw_line' => $line
                    ];
                }
            }
        }

        return $criticalErrors;
    }

    /**
     * Send critical error alerts to administrators
     */
    private function sendCriticalErrorAlerts(array $errors): int
    {
        $admins = User::where(function($query) {
            $query->where('role', 'admin')
                  ->orWhere('role', 'executive')
                  ->orWhere('role', 'manager');
        })->get();

        $alertsSent = 0;

        foreach ($admins as $admin) {
            try {
                // Log the alert (you can extend this to send emails, SMS, etc.)
                Log::channel('firebase-errors')->warning('Critical Error Alert Sent', [
                    'recipient' => $admin->email,
                    'admin_id' => $admin->id,
                    'error_count' => count($errors),
                    'errors_summary' => array_slice($errors, 0, 3), // 只記錄前3個錯誤的摘要
                    'sent_at' => now()->toISOString()
                ]);

                $alertsSent++;
            } catch (\Exception $e) {
                Log::error('Failed to send critical error alert', [
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $alertsSent;
    }

    /**
     * Get error pattern analysis for the dashboard
     */
    public function getErrorPatternAnalysis(int $hours = 24): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            return [
                'patterns' => [],
                'recommendations' => [],
                'status' => 'no_log_file'
            ];
        }

        $errors = $this->parseErrorsForPeriod($logPath, $hours);
        $patterns = $this->analyzeErrorPatterns($errors);
        $recommendations = $this->generateRecommendations($patterns);

        return [
            'patterns' => $patterns,
            'recommendations' => $recommendations,
            'analysis_period_hours' => $hours,
            'total_errors_analyzed' => count($errors),
            'analyzed_at' => now()->toISOString(),
            'status' => 'completed'
        ];
    }

    /**
     * Parse errors for a specific time period
     */
    private function parseErrorsForPeriod(string $logPath, int $hours): array
    {
        $content = file_get_contents($logPath);
        $lines = explode("\n", $content);
        $errors = [];
        $cutoffTime = Carbon::now()->subHours($hours);

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|CRITICAL|EMERGENCY|WARNING): (.+)/', $line, $matches)) {
                $timestamp = Carbon::parse($matches[1]);

                if ($timestamp->gte($cutoffTime)) {
                    $errors[] = [
                        'timestamp' => $matches[1],
                        'level' => $matches[2],
                        'message' => $matches[3]
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * Analyze error patterns
     */
    private function analyzeErrorPatterns(array $errors): array
    {
        $patterns = [
            'by_hour' => [],
            'by_type' => [],
            'frequent_messages' => [],
            'database_errors' => 0,
            'firebase_errors' => 0,
            'api_errors' => 0,
            'authentication_errors' => 0
        ];

        foreach ($errors as $error) {
            $hour = Carbon::parse($error['timestamp'])->format('H:00');
            $patterns['by_hour'][$hour] = ($patterns['by_hour'][$hour] ?? 0) + 1;
            $patterns['by_type'][$error['level']] = ($patterns['by_type'][$error['level']] ?? 0) + 1;

            $message = strtolower($error['message']);

            // 分類錯誤類型
            if (strpos($message, 'database') !== false || strpos($message, 'sql') !== false) {
                $patterns['database_errors']++;
            }
            if (strpos($message, 'firebase') !== false) {
                $patterns['firebase_errors']++;
            }
            if (strpos($message, 'api') !== false || strpos($message, 'route') !== false) {
                $patterns['api_errors']++;
            }
            if (strpos($message, 'unauthenticated') !== false || strpos($message, 'unauthorized') !== false) {
                $patterns['authentication_errors']++;
            }

            // 統計常見錯誤訊息
            $shortMessage = substr($error['message'], 0, 100);
            $patterns['frequent_messages'][$shortMessage] = ($patterns['frequent_messages'][$shortMessage] ?? 0) + 1;
        }

        // 排序常見錯誤訊息
        arsort($patterns['frequent_messages']);
        $patterns['frequent_messages'] = array_slice($patterns['frequent_messages'], 0, 10, true);

        return $patterns;
    }

    /**
     * Generate recommendations based on error patterns
     */
    private function generateRecommendations(array $patterns): array
    {
        $recommendations = [];

        // 資料庫相關建議
        if ($patterns['database_errors'] > 10) {
            $recommendations[] = [
                'type' => 'database',
                'priority' => 'high',
                'title' => '資料庫連接問題',
                'description' => '檢測到大量資料庫錯誤，建議檢查資料庫連接狀態和查詢效能',
                'actions' => [
                    '檢查資料庫服務器狀態',
                    '優化慢查詢',
                    '增加連接池配置',
                    '檢查資料庫索引'
                ]
            ];
        }

        // Firebase 相關建議
        if ($patterns['firebase_errors'] > 5) {
            $recommendations[] = [
                'type' => 'firebase',
                'priority' => 'medium',
                'title' => 'Firebase 連接問題',
                'description' => '檢測到 Firebase 認證或連接錯誤',
                'actions' => [
                    '檢查 Firebase 憑證',
                    '驗證 Firebase 配置',
                    '檢查網路連接性',
                    '更新 Firebase SDK'
                ]
            ];
        }

        // API 相關建議
        if ($patterns['api_errors'] > 20) {
            $recommendations[] = [
                'type' => 'api',
                'priority' => 'medium',
                'title' => 'API 路由問題',
                'description' => '檢測到多個 API 路由錯誤',
                'actions' => [
                    '檢查路由配置',
                    '驗證 API 端點',
                    '檢查中介軟體配置',
                    '更新路由快取'
                ]
            ];
        }

        // 認證相關建議
        if ($patterns['authentication_errors'] > 15) {
            $recommendations[] = [
                'type' => 'authentication',
                'priority' => 'high',
                'title' => '認證問題',
                'description' => '檢測到大量認證失敗',
                'actions' => [
                    '檢查 JWT 配置',
                    '驗證認證邏輯',
                    '檢查 session 設定',
                    '監控暴力破解攻擊'
                ]
            ];
        }

        return $recommendations;
    }
}