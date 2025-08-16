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
        try {
            // Check if the table exists first
            if (!Schema::hasTable('chat_conversations')) {
                throw new \Exception('Table chat_conversations does not exist');
            }

            // Check if the status column exists
            if (!Schema::hasColumn('chat_conversations', 'status')) {
                throw new \Exception('Column status does not exist in chat_conversations table');
            }

            // Get current table structure
            $columns = DB::select('DESCRIBE chat_conversations');
            $statusColumn = collect($columns)->where('Field', 'status')->first();
            
            if (!$statusColumn) {
                throw new \Exception('Status column not found in table description');
            }

            // Log current status column definition
            \Log::info('Current status column definition: ' . $statusColumn->Type);

            // Check if 'sent' and 'failed' are already in the enum
            if (str_contains($statusColumn->Type, 'sent') && str_contains($statusColumn->Type, 'failed')) {
                \Log::info('ENUM already contains sent and failed values, skipping migration');
                return;
            }

            // Update the status enum to include all values used in the application
            DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived', 'sent', 'failed') DEFAULT 'unread'");
            
            \Log::info('Successfully updated status ENUM definition');

            // Update any existing invalid status values to valid ones
            $invalidCount = DB::table('chat_conversations')
                ->whereNotIn('status', ['unread', 'read', 'replied', 'archived', 'sent', 'failed'])
                ->count();

            if ($invalidCount > 0) {
                $updated = DB::table('chat_conversations')
                    ->whereNotIn('status', ['unread', 'read', 'replied', 'archived', 'sent', 'failed'])
                    ->update(['status' => 'replied']);

                \Log::info("Updated {$updated} records with invalid status values to 'replied'");
            } else {
                \Log::info('No invalid status values found');
            }

            // Verify the migration worked
            $newColumns = DB::select('DESCRIBE chat_conversations');
            $newStatusColumn = collect($newColumns)->where('Field', 'status')->first();
            \Log::info('New status column definition: ' . $newStatusColumn->Type);

        } catch (\Exception $e) {
            \Log::error('Migration failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Check if the table exists
            if (!Schema::hasTable('chat_conversations')) {
                \Log::warning('Table chat_conversations does not exist, cannot rollback');
                return;
            }

            // First update any 'sent' or 'failed' statuses to 'replied' before removing them from enum
            $updatedSent = DB::table('chat_conversations')
                ->where('status', 'sent')
                ->update(['status' => 'replied']);

            $updatedFailed = DB::table('chat_conversations')
                ->where('status', 'failed')
                ->update(['status' => 'replied']);

            if ($updatedSent > 0 || $updatedFailed > 0) {
                \Log::info("Rollback: Updated {$updatedSent} 'sent' and {$updatedFailed} 'failed' statuses to 'replied'");
            }

            // Revert back to original enum values
            DB::statement("ALTER TABLE chat_conversations MODIFY status ENUM('unread', 'read', 'replied', 'archived') DEFAULT 'unread'");
            
            \Log::info('Successfully reverted status ENUM to original values');

        } catch (\Exception $e) {
            \Log::error('Rollback failed: ' . $e->getMessage());
            throw $e;
        }
    }
};