<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 記錄消息更新
        Schema::create('chat_conversation_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('line_user_id');
            $table->string('update_type'); // status_change, content_edit, etc.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->bigInteger('update_version');
            $table->timestamps();
            
            $table->index(['line_user_id', 'update_version']);
            $table->index('conversation_id');
        });
        
        // 記錄消息刪除
        Schema::create('chat_conversation_deletes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('line_user_id');
            $table->bigInteger('delete_version');
            $table->timestamp('deleted_at');
            
            $table->index(['line_user_id', 'delete_version']);
        });
    }
    
    public function down()
    {
        Schema::dropIfExists('chat_conversation_updates');
        Schema::dropIfExists('chat_conversation_deletes');
    }
};