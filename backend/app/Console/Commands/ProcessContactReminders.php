<?php

namespace App\Console\Commands;

use App\Models\CustomerContactSchedule;
use App\Models\CustomerActivity;
use App\Models\User;
use App\Services\ContactReminderService;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessContactReminders extends Command
{
    protected $signature = 'contact:process-reminders 
                            {--dry-run : Run without making changes}
                            {--force : Force processing even if already run today}';

    protected $description = 'Process contact reminders and handle overdue contacts';

    private ContactReminderService $reminderService;

    public function __construct(ContactReminderService $reminderService)
    {
        parent::__construct();
        $this->reminderService = $reminderService;
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('開始處理聯絡提醒系統...');

        if ($dryRun) {
            $this->warn('*** 乾跑模式 - 不會實際更新數據 ***');
        }

        $stats = [
            'reminders_sent' => 0,
            'overdue_processed' => 0,
            'long_term_created' => 0
        ];

        // 1. 處理需要發送提醒的聯絡計畫
        $stats['reminders_sent'] = $this->processReminders($dryRun);

        // 2. 處理逾期未聯絡的計畫
        if (!$dryRun) {
            $overdueStats = $this->reminderService->processOverdueContacts();
            $stats['overdue_processed'] = $overdueStats['processed'];
        }

        // 3. 處理長期未聯絡的客戶
        if (!$dryRun) {
            $stats['long_term_created'] = $this->reminderService->checkLongTermUncontacted();
        }

        $this->info('聯絡提醒處理完成！');
        $this->displayStats($stats);
    }

    private function processReminders($dryRun = false)
    {
        $this->info('檢查需要發送提醒的聯絡計畫...');

        // 取得需要提醒的聯絡計畫（明天之前且未發送提醒）
        $needingReminder = CustomerContactSchedule::needingReminder()
            ->with(['customer', 'user'])
            ->get();

        $this->info("找到 {$needingReminder->count()} 筆需要提醒的計畫");

        $sentCount = 0;

        foreach ($needingReminder as $schedule) {
            $this->line("處理提醒: {$schedule->customer->name} - {$schedule->scheduled_date}");

            if (!$dryRun) {
                // 標記提醒已發送
                $schedule->sendReminder();

                // 記錄活動
                CustomerActivity::create([
                    'customer_id' => $schedule->customer_id,
                    'user_id' => $schedule->user_id,
                    'activity_type' => CustomerActivity::TYPE_SYSTEM,
                    'description' => "系統提醒：明天需聯絡客戶（{$schedule->scheduled_date}）",
                    'ip_address' => '127.0.0.1', // 系統操作
                ]);

                // 使用提醒服務發送通知
                $this->reminderService->sendContactReminder($schedule);
                $sentCount++;
            }
        }

        return $sentCount;
    }

    private function displayStats(array $stats)
    {
        $this->info('');
        $this->info('=== 處理結果統計 ===');
        $this->info("提醒已發送: {$stats['reminders_sent']} 筆");
        $this->info("逾期已處理: {$stats['overdue_processed']} 筆");
        $this->info("長期未聯絡已處理: {$stats['long_term_created']} 筆");
        $this->info('');
    }
}