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
        Schema::table('chat_conversations', function (Blueprint $table) {
            // 加入全域版本號，用於追蹤整體數據變化
            $table->bigInteger('version')->default(0)->index()->after('id');
        });
        
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
        
        // 為現有數據設置初始版本號
        $this->setInitialVersions();
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn('version');
        });
        Schema::dropIfExists('chat_versions');
    }
    
    /**
     * 為現有數據設置初始版本號
     */
    private function setInitialVersions(): void
    {
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
        if ($version > 1) {
            DB::table('chat_versions')
                ->where('key', 'global')
                ->update(['version' => $version - 1]);
        }
    }
};