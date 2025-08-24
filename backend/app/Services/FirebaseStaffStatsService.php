<?php

namespace App\Services;

use Kreait\Firebase\Contract\Firestore;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FirebaseStaffStatsService
{
    protected $firestore;

    public function __construct(?Firestore $firestore = null)
    {
        $this->firestore = $firestore;
    }

    /**
     * 檢查 Firestore 是否可用
     */
    protected function isFirestoreAvailable(): bool
    {
        return $this->firestore !== null;
    }

    /**
     * 更新特定員工的未讀統計
     */
    public function updateStaffUnreadStats($staffId)
    {
        if (!$this->isFirestoreAvailable()) {
            Log::channel('firebase')->warning('Firestore not available, skipping staff stats update', [
                'staff_id' => $staffId
            ]);
            return false;
        }

        try {
            $staff = User::find($staffId);
            if (!$staff) {
                return false;
            }

            // 計算該員工負責客戶的未讀訊息統計
            $stats = $this->calculateStaffStats($staffId);

            // 更新 Firebase 中該員工的統計資料
            $this->firestore->collection('staff_unread_stats')
                ->document((string)$staffId)
                ->set($stats, ['merge' => true]);

            Log::channel('firebase')->info('Staff unread stats updated', [
                'staff_id' => $staffId,
                'total_unread' => $stats['totalUnread']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to update staff unread stats', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 更新所有員工的統計資料
     */
    public function updateAllStaffStats()
    {
        if (!$this->isFirestoreAvailable()) {
            Log::channel('firebase')->warning('Firestore not available, skipping all staff stats update');
            return false;
        }

        try {
            // 獲取所有有分配客戶的員工
            $staffUsers = User::whereHas('customers')->get();
            
            $updated = 0;
            $failed = 0;
            $allStaffStats = [];

            foreach ($staffUsers as $staff) {
                $stats = $this->calculateStaffStats($staff->id);
                
                // 更新個別員工統計
                try {
                    $this->firestore->collection('staff_unread_stats')
                        ->document((string)$staff->id)
                        ->set($stats, ['merge' => true]);
                    $updated++;
                    
                    // 收集用於全域統計
                    $allStaffStats[$staff->id] = [
                        'staffId' => $staff->id,
                        'staffName' => $staff->name,
                        'totalUnread' => $stats['totalUnread'],
                        'activeConversations' => $stats['activeConversations'],
                        'updated' => $stats['updated']
                    ];
                } catch (\Exception $e) {
                    $failed++;
                    Log::channel('firebase')->error('Failed to update individual staff stats', [
                        'staff_id' => $staff->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // 更新 admin 可存取的全域統計資料
            $this->updateAdminStaffOverview($allStaffStats);

            Log::channel('firebase')->info('All staff stats update completed', [
                'updated' => $updated,
                'failed' => $failed,
                'total_staff' => count($staffUsers)
            ]);

            return [
                'success' => true,
                'updated' => $updated,
                'failed' => $failed,
                'total_staff' => count($staffUsers)
            ];
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to update all staff stats', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 更新 admin 用戶的員工總覽統計
     */
    public function updateAdminStaffOverview($allStaffStats)
    {
        if (!$this->isFirestoreAvailable()) {
            return false;
        }

        try {
            $overviewData = [
                'totalStaff' => count($allStaffStats),
                'totalUnreadMessages' => array_sum(array_column($allStaffStats, 'totalUnread')),
                'totalActiveConversations' => array_sum(array_column($allStaffStats, 'activeConversations')),
                'staffDetails' => array_values($allStaffStats),
                'lastUpdated' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
                'generatedAt' => Carbon::now()->toISOString()
            ];

            $this->firestore->collection('admin_staff_overview')
                ->document('all_staff_stats')
                ->set($overviewData);

            Log::channel('firebase')->info('Admin staff overview updated', [
                'total_staff' => $overviewData['totalStaff'],
                'total_unread' => $overviewData['totalUnreadMessages']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to update admin staff overview', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 計算特定員工的統計資料
     */
    protected function calculateStaffStats($staffId)
    {
        // 獲取該員工負責的所有客戶
        $customerIds = Customer::where('assigned_to', $staffId)
            ->pluck('id')
            ->toArray();

        if (empty($customerIds)) {
            return [
                'staffId' => $staffId,
                'totalUnread' => 0,
                'activeConversations' => 0,
                'conversationDetails' => [],
                'updated' => new \Google\Cloud\Core\Timestamp(new \DateTime())
            ];
        }

        // 計算未讀訊息統計
        $conversationStats = DB::table('chat_conversations as cc')
            ->leftJoin('customers as c', 'cc.customer_id', '=', 'c.id')
            ->select([
                'cc.line_user_id',
                'c.name as customer_name',
                DB::raw('COUNT(CASE WHEN cc.status = "unread" AND cc.is_from_customer = 1 THEN 1 END) as unread_count'),
                DB::raw('MAX(cc.message_timestamp) as last_message_time'),
                DB::raw('COUNT(*) as total_messages')
            ])
            ->whereIn('cc.customer_id', $customerIds)
            ->whereNotNull('cc.line_user_id')
            ->groupBy('cc.line_user_id', 'c.name')
            ->havingRaw('unread_count > 0 OR MAX(cc.message_timestamp) > ?', [Carbon::now()->subDays(7)])
            ->get();

        $conversationDetails = [];
        $totalUnread = 0;

        foreach ($conversationStats as $stat) {
            $conversationDetails[] = [
                'lineUserId' => $stat->line_user_id,
                'customerName' => $stat->customer_name ?: '未知客戶',
                'unreadCount' => $stat->unread_count,
                'lastMessageTime' => $stat->last_message_time,
                'totalMessages' => $stat->total_messages
            ];
            $totalUnread += $stat->unread_count;
        }

        return [
            'staffId' => $staffId,
            'totalUnread' => $totalUnread,
            'activeConversations' => count($conversationDetails),
            'conversationDetails' => $conversationDetails,
            'updated' => new \Google\Cloud\Core\Timestamp(new \DateTime()),
            'generatedAt' => Carbon::now()->toISOString()
        ];
    }

    /**
     * 獲取特定員工的統計資料
     */
    public function getStaffStats($staffId)
    {
        if (!$this->isFirestoreAvailable()) {
            Log::channel('firebase')->warning('Firestore not available, returning empty stats');
            return null;
        }

        try {
            $document = $this->firestore->collection('staff_unread_stats')
                ->document((string)$staffId)
                ->snapshot();

            if ($document->exists()) {
                return $document->data();
            }

            return null;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to get staff stats', [
                'staff_id' => $staffId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 獲取所有員工統計（僅供 admin 使用）
     */
    public function getAllStaffStatsForAdmin()
    {
        if (!$this->isFirestoreAvailable()) {
            Log::channel('firebase')->warning('Firestore not available, returning empty admin stats');
            return null;
        }

        try {
            $document = $this->firestore->collection('admin_staff_overview')
                ->document('all_staff_stats')
                ->snapshot();

            if ($document->exists()) {
                return $document->data();
            }

            return null;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to get admin staff stats', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 清理過期的統計資料
     */
    public function cleanupOldStats($daysOld = 30)
    {
        if (!$this->isFirestoreAvailable()) {
            return false;
        }

        try {
            $cutoffDate = Carbon::now()->subDays($daysOld);
            $cleaned = 0;

            // 獲取所有統計文檔
            $documents = $this->firestore->collection('staff_unread_stats')
                ->documents();

            foreach ($documents as $document) {
                if ($document->exists()) {
                    $data = $document->data();
                    if (isset($data['updated']) && $data['updated']->get()->toDateTime() < $cutoffDate) {
                        $document->reference()->delete();
                        $cleaned++;
                    }
                }
            }

            Log::channel('firebase')->info('Old staff stats cleaned up', [
                'cleaned' => $cleaned,
                'days_old' => $daysOld
            ]);

            return $cleaned;
        } catch (\Exception $e) {
            Log::channel('firebase')->error('Failed to cleanup old stats', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}