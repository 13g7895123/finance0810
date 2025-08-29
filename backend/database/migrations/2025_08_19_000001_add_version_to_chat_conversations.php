<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 檢查 version 欄位是否已存在
        if (!Schema::hasColumn('chat_conversations', 'version')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                // 加入全域版本號，用於追蹤整體數據變化
                $table->bigInteger('version')->default(0)->index()->after('id');
            });
        }
        
        // 檢查 chat_versions 表是否已存在
        if (!Schema::hasTable('chat_versions')) {
            // 創建版本號表，用於追蹤全域版本
            Schema::create('chat_versions', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->bigInteger('version')->default(0);
                $table->timestamps();
            });
            
            // 初始化全域版本號
            DB::table('chat_versions')->insert([
                'key' => 'global',
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        // 為現有數據設置初始版本號
        $this->setInitialVersions();
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 檢查 version 欄位是否存在才刪除
        if (Schema::hasColumn('chat_conversations', 'version')) {
            Schema::table('chat_conversations', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
        
        // 刪除 chat_versions 表
        Schema::dropIfExists('chat_versions');
    }
    
    /**
     * 為現有數據設置初始版本號
     */
    private function setInitialVersions(): void
    {
        // 只有在 version 欄位存在且 chat_versions 表存在時才執行
        if (!Schema::hasColumn('chat_conversations', 'version') || !Schema::hasTable('chat_versions')) {
            return;
        }
        
        // 檢查是否已經有版本號設置（避免重複執行）
        $hasVersions = DB::table('chat_conversations')
            ->where('version', '>', 0)
            ->exists();
            
        if ($hasVersions) {
            return; // 已經設置過版本號，不需要重複執行
        }
        
        // 按時間順序為現有對話設置遞增的版本號
        $conversations = DB::table('chat_conversations')
            ->orderBy('message_timestamp', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id']);
        
        $version = 1;
        foreach ($conversations as $conversation) {
            DB::table('chat_conversations')
                ->where('id', $conversation->id)
                ->update(['version' => $version]);
            $version++;
        }
        
        // 更新全域版本號為最大版本號
        if ($version > 1 && DB::table('chat_versions')->where('key', 'global')->exists()) {
            DB::table('chat_versions')
                ->where('key', 'global')
                ->update(['version' => $version - 1]);
        }
    }
};