<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Services\ChatQueryCacheService;

/**
 * 聊天查詢性能基準測試
 */
class ChatQueryBenchmark
{
    private $cacheService;
    
    public function __construct()
    {
        // 初始化 Laravel 應用（如果在 Laravel 環境外運行）
        if (!App::bound('app')) {
            $app = require_once __DIR__ . '/../../bootstrap/app.php';
            $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
        }
        
        $this->cacheService = app(ChatQueryCacheService::class);
    }
    
    public function run()
    {
        echo "=== 聊天查詢性能基準測試 ===\n\n";
        
        // 準備測試數據
        $this->prepareTestData();
        
        // 測試原始查詢
        $this->benchmark('原始對話列表查詢', function () {
            return DB::table('chat_conversations')
                ->join('customers', 'chat_conversations.line_user_id', '=', 'customers.line_user_id')
                ->select([
                    'chat_conversations.line_user_id',
                    'chat_conversations.customer_id',
                    DB::raw('MAX(chat_conversations.message_timestamp) as last_message_time'),
                    DB::raw('COUNT(*) as message_count')
                ])
                ->groupBy('chat_conversations.line_user_id', 'chat_conversations.customer_id')
                ->orderBy('last_message_time', 'desc')
                ->limit(20)
                ->get();
        });
        
        // 測試優化後的查詢
        $this->benchmark('優化對話列表查詢（無緩存）', function () {
            return $this->cacheService->getConversationList(null, true);
        });
        
        // 測試緩存查詢
        $this->benchmark('緩存對話列表查詢', function () {
            return $this->cacheService->getConversationList(null, false);
        });
        
        // 測試未讀計數查詢
        $lineUserIds = DB::table('chat_conversations')
            ->distinct()
            ->limit(50)
            ->pluck('line_user_id')
            ->toArray();
            
        $this->benchmark('批量未讀計數查詢（原始）', function () use ($lineUserIds) {
            $counts = [];
            foreach ($lineUserIds as $lineUserId) {
                $counts[$lineUserId] = DB::table('chat_conversations')
                    ->where('line_user_id', $lineUserId)
                    ->where('status', 'unread')
                    ->where('is_from_customer', 1)
                    ->count();
            }
            return $counts;
        });
        
        $this->benchmark('批量未讀計數查詢（優化）', function () use ($lineUserIds) {
            return $this->cacheService->getUnreadCounts($lineUserIds, true);
        });
        
        $this->benchmark('批量未讀計數查詢（緩存）', function () use ($lineUserIds) {
            return $this->cacheService->getUnreadCounts($lineUserIds, false);
        });
        
        // 測試消息查詢
        $testLineUserId = $lineUserIds[0] ?? 'test_user';
        
        $this->benchmark('單一對話消息查詢（原始）', function () use ($testLineUserId) {
            return DB::table('chat_conversations')
                ->select(['id', 'message_content', 'message_timestamp', 'is_from_customer', 'status'])
                ->where('line_user_id', $testLineUserId)
                ->orderBy('message_timestamp', 'desc')
                ->limit(50)
                ->get();
        });
        
        $this->benchmark('單一對話消息查詢（優化）', function () use ($testLineUserId) {
            return $this->cacheService->getOptimizedMessages($testLineUserId, 50, 0);
        });
        
        echo "\n=== 測試完成 ===\n";
        $this->showIndexUsage();
    }
    
    private function benchmark($name, $callback, $iterations = 10)
    {
        $times = [];
        $results = [];
        
        // 預熱
        $callback();
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $result = $callback();
            $end = microtime(true);
            
            $times[] = ($end - $start) * 1000; // 轉換為毫秒
            $results[] = $result;
        }
        
        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);
        $median = $this->getMedian($times);
        
        // 計算結果數量（如果是集合）
        $resultCount = 0;
        if (!empty($results)) {
            $firstResult = $results[0];
            if (is_countable($firstResult)) {
                $resultCount = count($firstResult);
            } elseif (is_object($firstResult) && method_exists($firstResult, 'count')) {
                $resultCount = $firstResult->count();
            }
        }
        
        printf(
            "%-35s | 平均: %6.2fms | 最小: %6.2fms | 最大: %6.2fms | 中位數: %6.2fms | 結果數: %d\n",
            $name, $avg, $min, $max, $median, $resultCount
        );
    }
    
    private function getMedian($array)
    {
        sort($array);
        $count = count($array);
        $middle = floor($count / 2);
        
        if ($count % 2) {
            return $array[$middle];
        } else {
            return ($array[$middle - 1] + $array[$middle]) / 2;
        }
    }
    
    private function prepareTestData()
    {
        echo "準備測試數據...\n";
        
        // 檢查是否已有足夠的測試數據
        $conversationCount = DB::table('chat_conversations')->count();
        $customerCount = DB::table('customers')->count();
        
        echo "現有對話數: {$conversationCount}, 客戶數: {$customerCount}\n";
        
        if ($conversationCount < 100) {
            echo "警告: 測試數據不足，建議至少有 100 條對話記錄以獲得準確的性能測試結果\n";
        }
        
        echo "測試數據準備完成\n\n";
    }
    
    private function showIndexUsage()
    {
        echo "\n=== 索引使用情況 ===\n";
        
        try {
            $indexes = DB::select('SHOW INDEX FROM chat_conversations');
            
            echo "chat_conversations 表索引:\n";
            foreach ($indexes as $index) {
                printf("  %-30s | 欄位: %-20s | 唯一: %s\n", 
                    $index->Key_name, 
                    $index->Column_name, 
                    $index->Non_unique ? 'No' : 'Yes'
                );
            }
            
            // 檢查查詢計劃
            echo "\n查詢計劃分析:\n";
            $explains = [
                'SELECT line_user_id, MAX(message_timestamp) FROM chat_conversations GROUP BY line_user_id',
                'SELECT COUNT(*) FROM chat_conversations WHERE status = "unread" AND is_from_customer = 1',
                'SELECT * FROM chat_conversations WHERE version > 100 ORDER BY version'
            ];
            
            foreach ($explains as $sql) {
                $explain = DB::select("EXPLAIN {$sql}");
                echo "SQL: " . substr($sql, 0, 50) . "...\n";
                foreach ($explain as $row) {
                    printf("  表: %-20s | 類型: %-10s | 索引: %-20s | 掃描行數: %s\n",
                        $row->table ?? 'N/A',
                        $row->type ?? 'N/A', 
                        $row->key ?? 'NONE',
                        $row->rows ?? 'N/A'
                    );
                }
                echo "\n";
            }
            
        } catch (\Exception $e) {
            echo "無法獲取索引信息: " . $e->getMessage() . "\n";
        }
    }
}

// 執行測試
if (php_sapi_name() === 'cli') {
    $benchmark = new ChatQueryBenchmark();
    $benchmark->run();
} else {
    echo "此腳本只能在命令行環境中運行\n";
}