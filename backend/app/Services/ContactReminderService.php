<?php

namespace App\Services;

use App\Models\CustomerContactSchedule;
use App\Models\CustomerActivity;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ContactReminderService
{
    /**
     * 發送聯絡提醒通知
     */
    public function sendContactReminder(CustomerContactSchedule $schedule)
    {
        $user = $schedule->user;
        $customer = $schedule->customer;
        
        // 創建系統通知
        $this->createSystemNotification($user, [
            'type' => 'contact_reminder',
            'title' => '聯絡提醒',
            'message' => "請記得明天聯絡客戶：{$customer->name}",
            'data' => [
                'customer_id' => $customer->id,
                'schedule_id' => $schedule->id,
                'scheduled_date' => $schedule->scheduled_date,
                'contact_type' => $schedule->contact_type,
                'priority' => $schedule->priority,
            ],
            'priority' => $schedule->priority === 'high' ? 'high' : 'medium'
        ]);

        // 記錄日誌
        Log::info('聯絡提醒已發送', [
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'schedule_id' => $schedule->id,
            'scheduled_date' => $schedule->scheduled_date
        ]);
    }

    /**
     * 發送逾期警告
     */
    public function sendOverdueAlert(CustomerContactSchedule $schedule)
    {
        $user = $schedule->user;
        $customer = $schedule->customer;
        $daysPastDue = Carbon::parse($schedule->scheduled_date)->diffInDays(now());

        // 創建高優先級系統通知
        $this->createSystemNotification($user, [
            'type' => 'contact_overdue',
            'title' => '聯絡逾期警告',
            'message' => "客戶 {$customer->name} 的聯絡計畫已逾期 {$daysPastDue} 天，請立即處理",
            'data' => [
                'customer_id' => $customer->id,
                'schedule_id' => $schedule->id,
                'scheduled_date' => $schedule->scheduled_date,
                'days_overdue' => $daysPastDue,
            ],
            'priority' => 'high'
        ]);

        // 如果逾期超過3天，同時通知管理人員
        if ($daysPastDue >= 3) {
            $this->notifyManagers($schedule, $daysPastDue);
        }

        Log::warning('聯絡逾期警告已發送', [
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'schedule_id' => $schedule->id,
            'days_overdue' => $daysPastDue
        ]);
    }

    /**
     * 發送緊急警報給管理人員
     */
    public function sendCriticalAlert(CustomerContactSchedule $schedule)
    {
        $user = $schedule->user;
        $customer = $schedule->customer;
        $daysPastDue = Carbon::parse($schedule->scheduled_date)->diffInDays(now());

        // 獲取所有管理人員
        $managers = User::role(['admin', 'executive', 'manager'])->get();

        foreach ($managers as $manager) {
            $this->createSystemNotification($manager, [
                'type' => 'critical_overdue',
                'title' => '客戶聯絡嚴重逾期',
                'message' => "業務 {$user->name} 對客戶 {$customer->name} 的聯絡已逾期 {$daysPastDue} 天，系統已自動重新排程",
                'data' => [
                    'customer_id' => $customer->id,
                    'schedule_id' => $schedule->id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'customer_name' => $customer->name,
                    'days_overdue' => $daysPastDue,
                    'action_taken' => 'auto_reschedule'
                ],
                'priority' => 'critical'
            ]);
        }

        Log::critical('緊急逾期警報已發送給管理人員', [
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'schedule_id' => $schedule->id,
            'days_overdue' => $daysPastDue,
            'managers_notified' => $managers->count()
        ]);
    }

    /**
     * 通知管理人員
     */
    private function notifyManagers(CustomerContactSchedule $schedule, int $daysPastDue)
    {
        $user = $schedule->user;
        $customer = $schedule->customer;

        $managers = User::role(['manager', 'executive'])->get();

        foreach ($managers as $manager) {
            $this->createSystemNotification($manager, [
                'type' => 'staff_overdue_alert',
                'title' => '員工聯絡逾期提醒',
                'message' => "業務 {$user->name} 對客戶 {$customer->name} 的聯絡已逾期 {$daysPastDue} 天",
                'data' => [
                    'customer_id' => $customer->id,
                    'schedule_id' => $schedule->id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'customer_name' => $customer->name,
                    'days_overdue' => $daysPastDue,
                ],
                'priority' => 'high'
            ]);
        }
    }

    /**
     * 創建系統通知
     */
    private function createSystemNotification(User $user, array $data)
    {
        // 這裡可以整合現有的通知系統
        // 暫時先記錄到數據庫或緩存中，等待前端輪詢或WebSocket推送

        try {
            // 如果有通知表，可以存儲到數據庫
            // Notification::create([
            //     'user_id' => $user->id,
            //     'type' => $data['type'],
            //     'title' => $data['title'],
            //     'message' => $data['message'],
            //     'data' => json_encode($data['data']),
            //     'priority' => $data['priority'],
            //     'read_at' => null,
            // ]);

            // 暫時記錄到日誌，後續可以整合到通知系統
            Log::info('系統通知創建', [
                'user_id' => $user->id,
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'priority' => $data['priority']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('創建系統通知失敗', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 檢查並處理逾期聯絡
     */
    public function processOverdueContacts()
    {
        $overdueSchedules = CustomerContactSchedule::overdue()
            ->with(['customer', 'user'])
            ->get();

        $processedCount = 0;
        $rescheduledCount = 0;

        foreach ($overdueSchedules as $schedule) {
            $daysPastDue = Carbon::parse($schedule->scheduled_date)->diffInDays(now());

            if ($daysPastDue >= 3) {
                // 嚴重逾期：標記為未聯絡並重新排程
                $this->handleSeverelyOverdue($schedule);
                $rescheduledCount++;
            } else {
                // 輕微逾期：發送警告
                $this->sendOverdueAlert($schedule);
            }

            $processedCount++;
        }

        Log::info('逾期聯絡處理完成', [
            'total_processed' => $processedCount,
            'rescheduled' => $rescheduledCount
        ]);

        return [
            'processed' => $processedCount,
            'rescheduled' => $rescheduledCount
        ];
    }

    /**
     * 處理嚴重逾期的聯絡
     */
    private function handleSeverelyOverdue(CustomerContactSchedule $schedule)
    {
        // 標記原計畫為未聯絡
        $schedule->update(['status' => CustomerContactSchedule::STATUS_MISSED]);

        // 創建新的高優先級聯絡計畫
        $newSchedule = CustomerContactSchedule::create([
            'customer_id' => $schedule->customer_id,
            'user_id' => $schedule->user_id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => $schedule->scheduled_time,
            'status' => CustomerContactSchedule::STATUS_SCHEDULED,
            'contact_type' => $schedule->contact_type,
            'priority' => CustomerContactSchedule::PRIORITY_HIGH,
            'notes' => "系統自動重新排程：原計畫逾期 " . $schedule->scheduled_date,
        ]);

        // 記錄活動
        CustomerActivity::create([
            'customer_id' => $schedule->customer_id,
            'user_id' => $schedule->user_id,
            'activity_type' => CustomerActivity::TYPE_SYSTEM,
            'description' => "系統自動重新排程聯絡計畫（原計畫逾期超過3天）",
            'ip_address' => '127.0.0.1',
        ]);

        // 發送緊急警報
        $this->sendCriticalAlert($schedule);

        return $newSchedule;
    }

    /**
     * 檢查長期未聯絡的客戶
     */
    public function checkLongTermUncontacted()
    {
        // 查找超過30天沒有任何聯絡記錄的客戶
        $longTermCustomers = CustomerContactSchedule::where('status', 'missed')
            ->whereDoesntHave('customer.contactSchedules', function ($query) {
                $query->where('scheduled_date', '>=', now()->subDays(30))
                      ->whereIn('status', ['contacted', 'completed']);
            })
            ->with(['customer', 'user'])
            ->get()
            ->unique('customer_id');

        $createdCount = 0;

        foreach ($longTermCustomers as $schedule) {
            // 為長期未聯絡的客戶創建新的追蹤計畫
            CustomerContactSchedule::create([
                'customer_id' => $schedule->customer_id,
                'user_id' => $schedule->user_id,
                'scheduled_date' => now()->addDay()->toDateString(),
                'status' => CustomerContactSchedule::STATUS_SCHEDULED,
                'contact_type' => $schedule->contact_type,
                'priority' => CustomerContactSchedule::PRIORITY_HIGH,
                'notes' => "系統自動創建：客戶長期未聯絡（超過30天）",
            ]);

            // 記錄活動
            CustomerActivity::create([
                'customer_id' => $schedule->customer_id,
                'user_id' => $schedule->user_id,
                'activity_type' => CustomerActivity::TYPE_SYSTEM,
                'description' => "系統自動創建聯絡計畫：客戶長期未聯絡",
                'ip_address' => '127.0.0.1',
            ]);

            // 發送通知給業務和管理人員
            $this->sendLongTermUncontactedAlert($schedule);
            
            $createdCount++;
        }

        Log::info('長期未聯絡客戶處理完成', [
            'customers_found' => $longTermCustomers->count(),
            'schedules_created' => $createdCount
        ]);

        return $createdCount;
    }

    /**
     * 發送長期未聯絡警報
     */
    private function sendLongTermUncontactedAlert(CustomerContactSchedule $schedule)
    {
        $user = $schedule->user;
        $customer = $schedule->customer;

        // 通知業務人員
        $this->createSystemNotification($user, [
            'type' => 'long_term_uncontacted',
            'title' => '長期未聯絡客戶提醒',
            'message' => "客戶 {$customer->name} 超過30天未聯絡，系統已自動安排明天聯絡",
            'data' => [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'action_taken' => 'auto_schedule_created'
            ],
            'priority' => 'high'
        ]);

        // 通知管理人員
        $managers = User::role(['manager', 'executive'])->get();
        foreach ($managers as $manager) {
            $this->createSystemNotification($manager, [
                'type' => 'staff_long_term_uncontacted',
                'title' => '員工長期未聯絡客戶',
                'message' => "業務 {$user->name} 對客戶 {$customer->name} 超過30天未聯絡",
                'data' => [
                    'customer_id' => $customer->id,
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'customer_name' => $customer->name,
                ],
                'priority' => 'high'
            ]);
        }
    }
}