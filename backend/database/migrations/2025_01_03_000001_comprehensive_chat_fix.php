<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. 確保 chat_conversations 表存在並且有正確的 status enum
        if (Schema::hasTable('chat_conversations')) {
            // 更新 status enum 包含所有需要的值
            try {
                DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived', 'sent', 'failed', 'delivered', 'pending') DEFAULT 'unread'");
                \Log::info('Successfully updated chat_conversations status enum');
            } catch (\Exception $e) {
                \Log::warning('Failed to update status enum, it might already be correct: ' . $e->getMessage());
            }
            
            // 確保有 version 欄位
            if (!Schema::hasColumn('chat_conversations', 'version')) {
                Schema::table('chat_conversations', function (Blueprint $table) {
                    $table->unsignedBigInteger('version')->after('updated_at')->default(0)->index();
                });
            }
            
            // 確保有 version_updated_at 欄位
            if (!Schema::hasColumn('chat_conversations', 'version_updated_at')) {
                Schema::table('chat_conversations', function (Blueprint $table) {
                    $table->timestamp('version_updated_at')->after('version')->nullable()->index();
                });
            }
        }
        
        // 2. 建立 chat_versions 表 (如果不存在)
        if (!Schema::hasTable('chat_versions')) {
            Schema::create('chat_versions', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique()->index();
                $table->unsignedBigInteger('version')->default(0)->index();
                $table->timestamps();
            });

            // 插入初始全域版本記錄
            DB::table('chat_versions')->insert([
                'key' => 'global',
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            // 確保有初始記錄
            $globalVersion = DB::table('chat_versions')->where('key', 'global')->first();
            if (!$globalVersion) {
                DB::table('chat_versions')->insert([
                    'key' => 'global',
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        // 3. 建立 global_version_sequence 表作為回退方案
        if (!Schema::hasTable('global_version_sequence')) {
            Schema::create('global_version_sequence', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('current_version')->default(1);
                $table->timestamp('updated_at');
            });
            
            // 插入初始版本記錄
            DB::table('global_version_sequence')->insert([
                'current_version' => 1,
                'updated_at' => now()
            ]);
        }
        
        // 4. 為現有的聊天記錄設置初始版本
        $this->setInitialVersions();
        
        // 5. 添加必要的索引以提升查詢效能
        $this->addOptimizationIndexes();
    }
    
    public function down()
    {
        // 移除版本相關欄位
        if (Schema::hasColumn('chat_conversations', 'version')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->dropColumn(['version', 'version_updated_at']);
            });
        }
        
        // 刪除版本追蹤表
        Schema::dropIfExists('chat_versions');
        Schema::dropIfExists('global_version_sequence');
        
        // 恢復原始 status enum
        if (Schema::hasTable('chat_conversations')) {
            DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived') DEFAULT 'unread'");
        }
    }
    
    /**
     * 為現有數據設置初始版本
     */
    private function setInitialVersions()
    {
        if (Schema::hasTable('chat_conversations') && Schema::hasColumn('chat_conversations', 'version')) {
            try {
                // 重新檢查欄位存在性，確保 DDL 操作已完成
                $columns = Schema::getColumnListing('chat_conversations');
                $hasVersionUpdatedAt = in_array('version_updated_at', $columns);
                
                if ($hasVersionUpdatedAt) {
                    // 如果 version_updated_at 欄位存在，更新兩個欄位
                    $count = DB::table('chat_conversations')
                        ->where(function($query) {
                            $query->whereNull('version')
                                  ->orWhere('version', 0);
                        })
                        ->update([
                            'version' => 1,
                            'version_updated_at' => now()
                        ]);
                } else {
                    // 如果 version_updated_at 欄位不存在，只更新 version
                    $count = DB::table('chat_conversations')
                        ->where(function($query) {
                            $query->whereNull('version')
                                  ->orWhere('version', 0);
                        })
                        ->update(['version' => 1]);
                }
                
                if ($count > 0) {
                    \Log::info("Updated {$count} chat conversations with initial version", [
                        'has_version_updated_at' => $hasVersionUpdatedAt
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to set initial versions: ' . $e->getMessage());
                // 不拋出異常，讓 migration 繼續執行
            }
        }
    }
    
    /**
     * 添加查詢優化索引
     */
    private function addOptimizationIndexes()
    {
        if (Schema::hasTable('chat_conversations')) {
            try {
                // 檢查索引是否已存在，避免重複創建
                $indexes = DB::select("SHOW INDEXES FROM chat_conversations");
                $indexNames = collect($indexes)->pluck('Key_name')->toArray();
                
                Schema::table('chat_conversations', function (Blueprint $table) use ($indexNames) {
                    if (!in_array('idx_version_line_user', $indexNames)) {
                        $table->index(['version', 'line_user_id'], 'idx_version_line_user');
                    }
                    
                    if (!in_array('idx_status_customer', $indexNames)) {
                        $table->index(['status', 'is_from_customer'], 'idx_status_customer');
                    }
                    
                    if (!in_array('idx_timestamp_version', $indexNames)) {
                        $table->index(['message_timestamp', 'version'], 'idx_timestamp_version');
                    }
                });
                
                \Log::info('Added optimization indexes to chat_conversations');
            } catch (\Exception $e) {
                \Log::warning('Failed to add some indexes, they might already exist: ' . $e->getMessage());
            }
        }
    }
};