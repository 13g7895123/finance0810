<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('chat_versions')) {
            Schema::create('chat_versions', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique()->index();
                $table->unsignedBigInteger('version')->default(0)->index();
                $table->timestamps();
            });

            // Insert initial global version record
            DB::table('chat_versions')->insert([
                'key' => 'global',
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_versions');
    }
};