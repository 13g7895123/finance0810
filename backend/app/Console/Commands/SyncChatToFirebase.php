<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseSyncService;
use App\Services\FirebaseChatService;
use Illuminate\Support\Facades\Log;

class SyncChatToFirebase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:sync-firebase 
                            {--customer-id= : Sync specific customer ID}
                            {--line-user-id= : Sync specific LINE user ID}
                            {--batch-size=100 : Number of conversations to sync per batch}
                            {--validate : Validate data consistency after sync}
                            {--cleanup : Clean up expired Firebase data}
                            {--force : Force sync even if no recent changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync chat conversations from MySQL to Firebase';

    protected $firebaseSyncService;
    protected $firebaseChatService;

    public function __construct(FirebaseSyncService $firebaseSyncService, FirebaseChatService $firebaseChatService)
    {
        parent::__construct();
        $this->firebaseSyncService = $firebaseSyncService;
        $this->firebaseChatService = $firebaseChatService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔥 Starting Firebase chat sync...');

        // 檢查 Firebase 連接
        if (!$this->firebaseChatService->checkFirebaseConnection()) {
            $this->error('❌ Firebase connection failed. Please check your configuration.');
            return 1;
        }

        $this->info('✅ Firebase connection successful.');

        try {
            // 根據選項執行不同操作
            if ($this->option('cleanup')) {
                $this->handleCleanup();
            } elseif ($this->option('customer-id')) {
                $this->handleCustomerSync();
            } elseif ($this->option('line-user-id')) {
                $this->handleLineUserSync();
            } else {
                $this->handleBatchSync();
            }

            // 驗證資料一致性
            if ($this->option('validate')) {
                $this->handleValidation();
            }

            $this->info('🎉 Firebase sync completed successfully!');
            return 0;

        } catch (\Exception $e) {
            $this->error('💥 Sync failed: ' . $e->getMessage());
            Log::error('Firebase sync command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options' => $this->options()
            ]);
            return 1;
        }
    }

    /**
     * 處理批次同步
     */
    protected function handleBatchSync()
    {
        $batchSize = $this->option('batch-size');
        $this->info("📦 Starting batch sync (batch size: {$batchSize})...");

        $result = $this->firebaseChatService->batchSyncToFirebase($batchSize, 0);

        if ($result) {
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Synced', $result['synced']],
                    ['Failed', $result['failed']],
                    ['Total Processed', $result['total_processed']]
                ]
            );
        } else {
            $this->error('❌ Batch sync failed.');
        }
    }

    /**
     * 處理特定客戶同步
     */
    protected function handleCustomerSync()
    {
        $customerId = $this->option('customer-id');
        $this->info("👤 Syncing customer ID: {$customerId}...");

        $result = $this->firebaseSyncService->syncCustomerToFirebase($customerId);

        if ($result['success']) {
            $this->info("✅ Customer sync completed:");
            $this->line("   Synced: {$result['synced']}");
            $this->line("   Failed: {$result['failed']}");
        } else {
            $this->error("❌ Customer sync failed: {$result['error']}");
        }
    }

    /**
     * 處理特定 LINE 用戶同步
     */
    protected function handleLineUserSync()
    {
        $lineUserId = $this->option('line-user-id');
        $this->info("📱 Syncing LINE user: {$lineUserId}...");

        $result = $this->firebaseSyncService->syncMySQLToFirebase();

        if ($result['success']) {
            $this->info("✅ LINE user sync completed:");
            $this->line("   Synced: {$result['synced']}");
            $this->line("   Failed: {$result['failed']}");
        } else {
            $this->error("❌ LINE user sync failed: {$result['error']}");
        }
    }

    /**
     * 處理資料驗證
     */
    protected function handleValidation()
    {
        $this->info('🔍 Validating data consistency...');

        $lineUserId = $this->option('line-user-id');
        $result = $this->firebaseSyncService->validateDataConsistency($lineUserId);

        if ($result['success']) {
            $this->info("✅ Validation completed:");
            $this->line("   Checked: {$result['checked']} conversations");
            $this->line("   Issues found: {$result['issues_count']}");

            if ($result['issues_count'] > 0) {
                $this->warn("⚠️  Found {$result['issues_count']} consistency issues.");
                
                if ($this->confirm('Would you like to attempt automatic fixes?')) {
                    $fixResult = $this->firebaseSyncService->fixDataInconsistency($result['issues']);
                    $this->info("🔧 Fix completed:");
                    $this->line("   Fixed: {$fixResult['fixed']}");
                    $this->line("   Failed: {$fixResult['failed']}");
                }
            }
        } else {
            $this->error("❌ Validation failed: {$result['error']}");
        }
    }

    /**
     * 處理清理作業
     */
    protected function handleCleanup()
    {
        $this->info('🧹 Cleaning up expired Firebase data...');

        $days = $this->ask('How many days old should data be to clean up?', '30');
        
        if ($this->confirm("Are you sure you want to clean up data older than {$days} days?")) {
            $result = $this->firebaseSyncService->cleanupExpiredFirebaseData((int)$days);

            if ($result['success']) {
                $this->info("✅ Cleanup completed:");
                $this->line("   Cleaned: {$result['cleaned']} conversations");
                $this->line("   Failed: {$result['failed']}");
                $this->line("   Cutoff date: {$result['cutoff_date']}");
            } else {
                $this->error("❌ Cleanup failed: {$result['error']}");
            }
        } else {
            $this->info('Cleanup cancelled.');
        }
    }
}