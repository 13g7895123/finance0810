<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 檢查欄位是否已存在
            if (!Schema::hasColumn('customers', 'customer_level')) {
                $table->enum('customer_level', ['A', 'B', 'C'])->default('B')->after('priority_level');
            }

            // 檢查索引是否已存在
            $hasIndex = false;
            $indexes = Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('customers');
            foreach ($indexes as $index) {
                $columns = $index->getColumns();
                if ($columns === ['customer_level', 'assigned_to']) {
                    $hasIndex = true;
                    break;
                }
            }

            if (!$hasIndex) {
                try {
                    $table->index(['customer_level', 'assigned_to']);
                } catch (\Exception $e) {
                    // Index may already exist, ignore
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['customer_level', 'assigned_to']);
            $table->dropColumn('customer_level');
        });
    }
};