<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. 確保 chat_conversations 表存在並具有正確的結構
        if (Schema::hasTable('chat_conversations')) {
            \Log::info('Fixing chat_conversations table schema...');
            
            // 先檢查並修復 status enum
            try {
                DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived', 'sent', 'failed', 'delivered', 'pending') DEFAULT 'unread'");
                \Log::info('Fixed chat_conversations status enum');
            } catch (\Exception $e) {
                \Log::warning('Status enum might already be correct: ' . $e->getMessage());
            }
            
            // 檢查並添加 version 欄位
            if (!Schema::hasColumn('chat_conversations', 'version')) {
                Schema::table('chat_conversations', function (Blueprint $table) {
                    $table->unsignedBigInteger('version')->after('updated_at')->default(1)->index();
                });
                \Log::info('Added version column to chat_conversations');
            }
            
            // 檢查並添加 version_updated_at 欄位
            if (!Schema::hasColumn('chat_conversations', 'version_updated_at')) {
                Schema::table('chat_conversations', function (Blueprint $table) {
                    $table->timestamp('version_updated_at')->after('version')->nullable()->index();
                });
                \Log::info('Added version_updated_at column to chat_conversations');
            }
        }
        
        // 2. 確保 chat_versions 表存在
        if (!Schema::hasTable('chat_versions')) {
            Schema::create('chat_versions', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique()->index();
                $table->unsignedBigInteger('version')->default(0)->index();
                $table->timestamps();
            });
            \Log::info('Created chat_versions table');
        }
        
        // 3. 確保 chat_versions 表有初始數據
        try {
            $globalVersion = DB::table('chat_versions')->where('key', 'global')->first();
            if (!$globalVersion) {
                DB::table('chat_versions')->insert([
                    'key' => 'global',
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                \Log::info('Inserted initial global version record');
            }
        } catch (\Exception $e) {
            \Log::warning('Could not insert initial version record: ' . $e->getMessage());
        }
        
        // 4. 安全地初始化現有對話記錄的版本號
        $this->safeInitializeVersions();
        
        // 5. 添加必要的索引
        $this->addOptimizationIndexes();
    }
    
    public function down()
    {
        // 移除新添加的欄位和表
        if (Schema::hasColumn('chat_conversations', 'version_updated_at')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->dropColumn('version_updated_at');
            });
        }
        
        if (Schema::hasColumn('chat_conversations', 'version')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
        
        // 恢復原始 status enum
        if (Schema::hasTable('chat_conversations')) {
            try {
                DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived') DEFAULT 'unread'");
            } catch (\Exception $e) {
                \Log::warning('Could not revert status enum: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * 安全地初始化版本號
     */
    private function safeInitializeVersions()
    {
        if (!Schema::hasTable('chat_conversations')) {
            return;
        }
        
        try {
            // 刷新 schema 緩存並重新檢查欄位
            Schema::getConnection()->getDoctrineSchemaManager()->listTableColumns('chat_conversations');
            $columns = Schema::getColumnListing('chat_conversations');
            
            $hasVersion = in_array('version', $columns);
            $hasVersionUpdatedAt = in_array('version_updated_at', $columns);
            
            \Log::info('Column check results', [
                'has_version' => $hasVersion,
                'has_version_updated_at' => $hasVersionUpdatedAt
            ]);
            
            if ($hasVersion) {
                // 構建更新數據
                $updateData = ['version' => 1];
                if ($hasVersionUpdatedAt) {
                    $updateData['version_updated_at'] = now();
                }
                
                // 分批處理以避免鎖定表太久
                $batchSize = 1000;
                do {
                    $updated = DB::table('chat_conversations')
                        ->where(function($query) {
                            $query->whereNull('version')
                                  ->orWhere('version', 0);
                        })
                        ->limit($batchSize)
                        ->update($updateData);
                    
                    if ($updated > 0) {
                        \Log::info("Updated {$updated} records in this batch");
                    }
                } while ($updated > 0);
                
                // 檢查總更新數量
                $totalCount = DB::table('chat_conversations')
                    ->where('version', '>', 0)
                    ->count();
                    
                \Log::info("Total versioned conversations: {$totalCount}");
            }
            
        } catch (\Exception $e) {
            \Log::error('Failed to initialize versions safely', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 添加查詢優化索引
     */
    private function addOptimizationIndexes()
    {
        if (!Schema::hasTable('chat_conversations')) {
            return;
        }
        
        try {
            // 檢查現有索引
            $indexes = DB::select("SHOW INDEXES FROM chat_conversations");
            $indexNames = collect($indexes)->pluck('Key_name')->toArray();
            
            Schema::table('chat_conversations', function (Blueprint $table) use ($indexNames) {
                if (!in_array('idx_line_user_timestamp', $indexNames)) {
                    $table->index(['line_user_id', 'message_timestamp'], 'idx_line_user_timestamp');
                }
                
                if (!in_array('idx_status_customer_timestamp', $indexNames)) {
                    $table->index(['status', 'is_from_customer', 'message_timestamp'], 'idx_status_customer_timestamp');
                }
                
                if (!in_array('idx_version_line_user', $indexNames)) {
                    $table->index(['version', 'line_user_id'], 'idx_version_line_user');
                }
            });
            
            \Log::info('Added optimization indexes successfully');
            
        } catch (\Exception $e) {
            \Log::warning('Some indexes might already exist: ' . $e->getMessage());
        }
    }
};