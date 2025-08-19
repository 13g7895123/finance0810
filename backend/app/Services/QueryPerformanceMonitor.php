<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryPerformanceMonitor
{
    private $slowQueryThreshold = 100; // 毫秒
    private $isMonitoring = false;
    
    /**
     * 監控查詢性能
     */
    public function monitor()
    {
        if ($this->isMonitoring) {
            return; // 避免重複監控
        }
        
        $this->isMonitoring = true;
        
        DB::listen(function ($query) {
            if ($query->time > $this->slowQueryThreshold) {
                Log::warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                    'connection' => $query->connectionName
                ]);
                
                // 分析查詢計劃
                $this->analyzeQuery($query->sql, $query->bindings);
            }
        });
    }
    
    /**
     * 分析查詢計劃
     */
    private function analyzeQuery($sql, $bindings)
    {
        try {
            // 準備 SQL - 將佔位符替換為實際值
            $analyzableSql = $this->prepareSqlForAnalysis($sql, $bindings);
            
            // 執行 EXPLAIN
            $explain = DB::select("EXPLAIN {$analyzableSql}");
            
            Log::info('Query execution plan', [
                'sql' => $analyzableSql,
                'explain' => $explain
            ]);
            
            // 檢查是否使用索引
            foreach ($explain as $row) {
                if (empty($row->key) || $row->key === null) {
                    Log::warning('Query not using index!', [
                        'table' => $row->table ?? 'unknown',
                        'type' => $row->type ?? 'unknown',
                        'rows' => $row->rows ?? 'unknown',
                        'extra' => $row->Extra ?? 'none'
                    ]);
                }
                
                // 檢查是否進行全表掃描
                if (isset($row->type) && $row->type === 'ALL') {
                    Log::warning('Full table scan detected!', [
                        'table' => $row->table ?? 'unknown',
                        'rows' => $row->rows ?? 'unknown'
                    ]);
                }
                
                // 檢查是否掃描行數過多
                if (isset($row->rows) && $row->rows > 1000) {
                    Log::warning('High row scan count', [
                        'table' => $row->table ?? 'unknown',
                        'rows' => $row->rows,
                        'type' => $row->type ?? 'unknown'
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to analyze query', [
                'sql' => $sql,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 準備 SQL 用於分析
     */
    private function prepareSqlForAnalysis($sql, $bindings)
    {
        try {
            // 將 ? 佔位符替換為實際值
            $bindingIndex = 0;
            $analyzableSql = preg_replace_callback('/\?/', function ($matches) use ($bindings, &$bindingIndex) {
                if (!isset($bindings[$bindingIndex])) {
                    return '?';
                }
                
                $value = $bindings[$bindingIndex++];
                
                if (is_string($value)) {
                    return "'" . addslashes($value) . "'";
                } elseif (is_null($value)) {
                    return 'NULL';
                } elseif (is_bool($value)) {
                    return $value ? '1' : '0';
                } else {
                    return $value;
                }
            }, $sql);
            
            return $analyzableSql;
        } catch (\Exception $e) {
            Log::error('Failed to prepare SQL for analysis', [
                'sql' => $sql,
                'error' => $e->getMessage()
            ]);
            return $sql; // 回退到原始 SQL
        }
    }
    
    /**
     * 獲取查詢性能統計
     */
    public function getQueryStats()
    {
        try {
            // 查詢慢查詢日誌統計（如果可用）
            $slowQueries = DB::select("
                SELECT COUNT(*) as slow_query_count
                FROM information_schema.processlist 
                WHERE time > ?
            ", [$this->slowQueryThreshold / 1000]);
            
            return [
                'slow_query_threshold' => $this->slowQueryThreshold,
                'monitoring_enabled' => $this->isMonitoring,
                'current_slow_queries' => $slowQueries[0]->slow_query_count ?? 0
            ];
        } catch (\Exception $e) {
            return [
                'slow_query_threshold' => $this->slowQueryThreshold,
                'monitoring_enabled' => $this->isMonitoring,
                'error' => 'Failed to get query stats: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 設置慢查詢閾值
     */
    public function setSlowQueryThreshold($milliseconds)
    {
        $this->slowQueryThreshold = max(1, (int)$milliseconds);
    }
    
    /**
     * 檢查表索引使用情況
     */
    public function checkIndexUsage($tableName)
    {
        try {
            // 獲取表的索引信息
            $indexes = DB::select("SHOW INDEX FROM {$tableName}");
            
            // 獲取索引統計信息（MySQL 5.6+）
            $indexStats = DB::select("
                SELECT 
                    index_name,
                    rows_selected,
                    rows_inserted,
                    rows_updated,
                    rows_deleted
                FROM performance_schema.table_io_waits_summary_by_index_usage 
                WHERE object_schema = DATABASE() 
                AND object_name = ?
            ", [$tableName]);
            
            return [
                'indexes' => $indexes,
                'usage_stats' => $indexStats
            ];
        } catch (\Exception $e) {
            Log::error('Failed to check index usage', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'error' => 'Failed to check index usage: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 停止監控
     */
    public function stopMonitoring()
    {
        $this->isMonitoring = false;
    }
}