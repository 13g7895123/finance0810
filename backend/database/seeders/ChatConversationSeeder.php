<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ChatConversation;
use App\Models\Customer;
use App\Models\User;

class ChatConversationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 創建測試客戶
        $customers = [
            [
                'name' => '劉柏毅',
                'phone' => '0912345678',
                'email' => 'liu@example.com',
                'region' => '台北',
                'website_source' => '熊好貸',
                'channel' => 'line',
                'status' => 'new',
                'line_user_id' => 'U100',
                'line_display_name' => '劉柏毅',
                'assigned_to' => 1, // 假設有 ID 1 的用戶
            ],
            [
                'name' => 'CSL',
                'phone' => '0923456789',
                'email' => 'csl@example.com',
                'region' => '新北',
                'website_source' => '網站A',
                'channel' => 'line',
                'status' => 'contacted',
                'line_user_id' => 'U101',
                'line_display_name' => 'CSL',
                'assigned_to' => 1,
            ],
            [
                'name' => 'Daniel',
                'phone' => '0934567890',
                'email' => 'daniel@example.com',
                'region' => '桃園',
                'website_source' => '熊好貸',
                'channel' => 'line',
                'status' => 'interested',
                'line_user_id' => 'U102',
                'line_display_name' => 'Daniel',
                'assigned_to' => 2,
            ],
            [
                'name' => '暴色水母',
                'phone' => '0945678901',
                'email' => 'jellyfish@example.com',
                'region' => '台中',
                'website_source' => '網站B',
                'channel' => 'line',
                'status' => 'new',
                'line_user_id' => 'U103',
                'line_display_name' => '暴色水母',
                'assigned_to' => 2,
            ],
            [
                'name' => '晞晞',
                'phone' => '0967890123',
                'email' => 'xixi@example.com',
                'region' => '台南',
                'website_source' => '熊好貸',
                'channel' => 'line',
                'status' => 'converted',
                'line_user_id' => 'U105',
                'line_display_name' => '晞晞',
                'assigned_to' => 1,
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::firstOrCreate(
                ['line_user_id' => $customerData['line_user_id']],
                $customerData
            );
        }

        // 創建測試對話記錄
        $conversations = [
            // 劉柏毅的對話
            [
                'customer_id' => Customer::where('line_user_id', 'U100')->first()->id,
                'user_id' => 1,
                'line_user_id' => 'U100',
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '你好',
                'message_timestamp' => now()->subMinutes(30),
                'is_from_customer' => true,
                'status' => 'read',
            ],
            [
                'customer_id' => Customer::where('line_user_id', 'U100')->first()->id,
                'user_id' => 1,
                'line_user_id' => 'U100',
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '您好！歡迎來到熊好貸，我是您的專屬服務助手。請問您需要什麼貸款服務呢？',
                'message_timestamp' => now()->subMinutes(29),
                'is_from_customer' => false,
                'reply_content' => '您好！歡迎來到熊好貸，我是您的專屬服務助手。請問您需要什麼貸款服務呢？',
                'replied_at' => now()->subMinutes(29),
                'replied_by' => 1,
                'status' => 'replied',
            ],
            [
                'customer_id' => Customer::where('line_user_id', 'U100')->first()->id,
                'user_id' => 1,
                'line_user_id' => 'U100',
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '請問汽車貸款的利率是多少？',
                'message_timestamp' => now()->subMinutes(5),
                'is_from_customer' => true,
                'status' => 'unread',
            ],

            // CSL的對話
            [
                'customer_id' => Customer::where('line_user_id', 'U101')->first()->id,
                'user_id' => 1,
                'line_user_id' => 'U101',
                'platform' => 'line',
                'message_type' => 'sticker',
                'message_content' => 'https://stickershop.line-scdn.net/stickershop/v1/sticker/52002734/android/sticker.png',
                'message_timestamp' => now()->subHours(2),
                'is_from_customer' => true,
                'status' => 'read',
            ],
            [
                'customer_id' => Customer::where('line_user_id', 'U101')->first()->id,
                'user_id' => 1,
                'line_user_id' => 'U101',
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '感謝您的訊息！如有任何貸款需求或問題，歡迎隨時聯繫我們。',
                'message_timestamp' => now()->subHours(2)->addSeconds(30),
                'is_from_customer' => false,
                'reply_content' => '感謝您的訊息！如有任何貸款需求或問題，歡迎隨時聯繫我們。',
                'replied_at' => now()->subHours(2)->addSeconds(30),
                'replied_by' => 1,
                'status' => 'replied',
            ],

            // Daniel的對話 (多則訊息測試滾動條)
            [
                'customer_id' => Customer::where('line_user_id', 'U102')->first()->id,
                'user_id' => 2,
                'line_user_id' => 'U102',
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '感謝客服的文字',
                'message_timestamp' => now()->subMinutes(10),
                'is_from_customer' => true,
                'status' => 'unread',
            ],
            [
                'customer_id' => Customer::where('line_user_id', 'U102')->first()->id,
                'user_id' => 2,
                'line_user_id' => 'U102',
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '我想了解更多關於汽車貸款的詳細信息',
                'message_timestamp' => now()->subMinutes(8),
                'is_from_customer' => true,
                'status' => 'unread',
            ],

            // 暴色水母的對話
            [
                'customer_id' => Customer::where('line_user_id', 'U103')->first()->id,
                'user_id' => 2,
                'line_user_id' => 'U103',
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => 'ooo',
                'message_timestamp' => now()->subMinutes(15),
                'is_from_customer' => true,
                'status' => 'read',
            ],
            [
                'customer_id' => Customer::where('line_user_id', 'U103')->first()->id,
                'user_id' => 2,
                'line_user_id' => 'U103',
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '您好！請問有什麼我可以協助您的嗎？我們提供汽車、機車、手機貸款等服務。',
                'message_timestamp' => now()->subMinutes(14),
                'is_from_customer' => false,
                'reply_content' => '您好！請問有什麼我可以協助您的嗎？我們提供汽車、機車、手機貸款等服務。',
                'replied_at' => now()->subMinutes(14),
                'replied_by' => 2,
                'status' => 'replied',
            ],

            // 晞晞的對話
            [
                'customer_id' => Customer::where('line_user_id', 'U105')->first()->id,
                'user_id' => 1,
                'line_user_id' => 'U105',
                'platform' => 'line',
                'message_type' => 'file',
                'message_content' => 'https://storage.googleapis.com/line-bot/26VFC1752485624',
                'message_timestamp' => now()->subDays(30),
                'is_from_customer' => true,
                'status' => 'read',
            ],
            [
                'customer_id' => Customer::where('line_user_id', 'U105')->first()->id,
                'user_id' => 1,
                'line_user_id' => 'U105',
                'platform' => 'line',
                'message_type' => 'text',
                'message_content' => '感謝您提供的資訊。我已經收到您的訊息，專員會盡快與您聯繫。',
                'message_timestamp' => now()->subDays(30)->addMinute(),
                'is_from_customer' => false,
                'reply_content' => '感謝您提供的資訊。我已經收到您的訊息，專員會盡快與您聯繫。',
                'replied_at' => now()->subDays(30)->addMinute(),
                'replied_by' => 1,
                'status' => 'replied',
            ],
        ];

        foreach ($conversations as $conversation) {
            ChatConversation::create($conversation);
        }

        $this->command->info('Chat conversations seeded successfully!');
        $this->command->info('Created ' . count($customers) . ' customers and ' . count($conversations) . ' chat messages');
    }
}