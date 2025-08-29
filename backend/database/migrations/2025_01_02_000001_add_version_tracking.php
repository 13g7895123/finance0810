<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 為 chat_conversations 表添加版本字段 (檢查是否已存在)
        if (!Schema::hasColumn('chat_conversations', 'version')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->unsignedBigInteger('version')->after('updated_at')->index();
                $table->timestamp('version_updated_at')->after('version')->index();
            });
        }
        
        // 為 customers 表添加版本字段 (檢查是否已存在)
        if (!Schema::hasColumn('customers', 'version')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedBigInteger('version')->default(1)->after('updated_at')->index();
                $table->timestamp('version_updated_at')->nullable()->after('version')->index();
            });
        }
        
        // 創建版本管理表 (檢查是否已存在)
        if (!Schema::hasTable('version_tracking')) {
            Schema::create('version_tracking', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 50)->index(); // 'chat', 'customer', etc.
                $table->unsignedBigInteger('entity_id')->index();
                $table->unsignedBigInteger('version');
                $table->string('operation', 20); // 'create', 'update', 'delete'
                $table->json('changes')->nullable(); // 變更內容
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamp('created_at');
                
                $table->index(['entity_type', 'entity_id']);
                $table->index(['entity_type', 'version']);
                $table->index('created_at');
            });
        }
        
        // 創建全局版本序列表 (檢查是否已存在)
        if (!Schema::hasTable('global_version_sequence')) {
            Schema::create('global_version_sequence', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('current_version')->default(0);
                $table->timestamp('updated_at');
            });
            
            // 插入初始版本記錄
            DB::table('global_version_sequence')->insert([
                'current_version' => 1,
                'updated_at' => now()
            ]);
        }
        
        // 為現有數據設置初始版本
        $this->setInitialVersions();
    }
    
    public function down()
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['version', 'version_updated_at']);
        });
        
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['version', 'version_updated_at']);
        });
        
        Schema::dropIfExists('version_tracking');
        Schema::dropIfExists('global_version_sequence');
    }
    
    /**
     * 為現有數據設置初始版本
     */
    private function setInitialVersions()
    {
        // 為現有聊天記錄設置版本 (只有在欄位存在時才更新)
        if (Schema::hasColumn('chat_conversations', 'version') && Schema::hasColumn('chat_conversations', 'version_updated_at')) {
            DB::table('chat_conversations')
                ->whereNull('version')
                ->update([
                    'version' => 1,
                    'version_updated_at' => now()
                ]);
        }
        
        // 為現有客戶設置版本 (只有在欄位存在時才更新)
        if (Schema::hasColumn('customers', 'version') && Schema::hasColumn('customers', 'version_updated_at')) {
            DB::table('customers')
                ->whereNull('version')
                ->update([
                    'version' => 1,
                    'version_updated_at' => now()
                ]);
        }
    }
};