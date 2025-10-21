<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerLead;
use App\Models\Customer;
use App\Models\CustomerIdentifier;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class TestDataController extends Controller
{
    /**
     * Point 4: Generate random WP lead data for testing
     * GET /api/test/generate-wp-lead
     *
     * This endpoint creates a random web lead with varied data each time,
     * simulating a real WP form submission for testing notifications.
     */
    public function generateRandomWpLead(Request $request)
    {
        $faker = Faker::create('zh_TW');
        $fakerEn = Faker::create('en_US'); // For email generation

        try {
            DB::beginTransaction();

            // Generate random customer data
            $name = $faker->name();
            $phone = '09' . $faker->numberBetween(10000000, 99999999);
            $email = $fakerEn->optional(0.7)->safeEmail(); // Use English locale for email
            $lineId = $faker->optional(0.5)->bothify('line_###???');

            // Random website domains for testing
            $websites = [
                'https://easypay-life.com.tw/',
                'https://mrmoney.com.tw/',
                'https://test-finance.com/',
                'https://demo-loan.tw/',
            ];
            $source = $faker->randomElement($websites);

            // Random contact times
            $contactTimes = [
                '上午9:00-12:00',
                '下午13:00-17:00',
                '晚上18:00-21:00',
                '任何時間',
            ];
            $contactTime = $faker->randomElement($contactTimes);

            // Random capital needs
            $capitalNeeds = ['10萬以下', '10-30萬', '30-50萬', '50-100萬', '100萬以上'];
            $capitalNeed = $faker->randomElement($capitalNeeds);

            // Random loan types
            $loanTypes = ['汽車貸款', '機車貸款', '手機貸款', '二胎房貸', '信用貸款'];
            $loanType = $faker->randomElement($loanTypes);

            // Random regions
            $regions = ['臺北市', '新北市', '桃園市', '臺中市', '高雄市', '臺南市'];
            $region = $faker->randomElement($regions);

            // Random education levels
            $educationLevels = ['高中職', '專科', '大學', '碩士', '博士'];
            $educationLevel = $faker->randomElement($educationLevels);

            // Random job titles
            $jobTitles = ['工程師', '業務', '經理', '主管', '專員', '技術員', '設計師'];
            $jobTitle = $faker->randomElement($jobTitles);

            // Random telecom providers
            $telecomProviders = ['中華電信', '台灣大哥大', '遠傳電信', '台灣之星', '亞太電信'];
            $telecomProvider = $faker->randomElement($telecomProviders);

            // Prepare payload data
            $payload = [
                '姓名' => $name,
                '手機號碼' => $phone,
                '方便聯絡時間' => $contactTime,
                '資金需求' => $capitalNeed,
                '貸款需求' => $loanType,
                '房屋區域' => $region,
                '日期' => now()->format('d F, Y'),
                '時間' => now()->format('g:i A'),
                '頁面 URL' => $source,
                'IP' => $request->ip(),
                'User-Agent' => 'Test Generator',
            ];

            if ($email) {
                $payload['Email'] = $email;
            }

            if ($lineId) {
                $payload['LINE_ID'] = $lineId;
            }

            // Create or find customer
            $identifierValues = [];
            if ($lineId) $identifierValues['line'] = trim($lineId);
            if ($phone) $identifierValues['phone'] = preg_replace('/\D+/', '', $phone);
            if ($email) $identifierValues['email'] = strtolower(trim($email));

            $existingCustomer = null;
            if (!empty($identifierValues)) {
                $existingCustomer = Customer::query()
                    ->whereHas('identifiers', function ($q) use ($identifierValues) {
                        $q->where(function ($qq) use ($identifierValues) {
                            foreach ($identifierValues as $type => $value) {
                                $qq->orWhere(function ($qqq) use ($type, $value) {
                                    $qqq->where('type', $type)->where('value', $value);
                                });
                            }
                        });
                    })->first();
            }

            if (!$existingCustomer) {
                $existingCustomer = Customer::create([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'region' => $region,
                    'channel' => 'web_form',
                    'website_source' => parse_url($source, PHP_URL_HOST),
                    'status' => Customer::STATUS_NEW,
                    'address' => $faker->address(),
                    'notes' => "方便聯絡時間：{$contactTime}",
                    'source_data' => [
                        'page_url' => $source,
                        'loan_need' => $loanType,
                        'capital_need' => $capitalNeed,
                        'website_domain' => parse_url($source, PHP_URL_HOST),
                        'submit_datetime' => now()->format('Y-m-d H:i:s'),
                        'raw_payload' => $payload,
                        'generated_by' => 'test_api',
                    ],
                ]);

                // Bind identifiers
                foreach ($identifierValues as $type => $value) {
                    CustomerIdentifier::firstOrCreate([
                        'type' => $type,
                        'value' => $value,
                    ], [
                        'customer_id' => $existingCustomer->id,
                    ]);
                }
            }

            // Create customer lead with random data
            $lead = CustomerLead::create([
                'customer_id' => $existingCustomer->id,
                'assigned_to' => null,
                'channel' => 'wp_form', // Fixed: always wp_form for web leads
                'source' => $source,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'line_id' => $lineId,
                'ip_address' => $request->ip(),
                'user_agent' => 'Test Data Generator API',
                'payload' => $payload,
                'status' => 'pending', // Fixed: always pending for new leads
                'is_suspected_blacklist' => false,

                // Random personal information
                'birth_date' => $faker->optional(0.3)->date('Y-m-d', '-30 years'),
                'id_number' => $faker->optional(0.2)->bothify('?#########'),
                'education_level' => $faker->optional(0.5)->randomElement($educationLevels),

                // Random contact information
                'contact_time' => $contactTime,
                'registered_address' => $faker->optional(0.6)->address(),
                'home_phone' => $faker->optional(0.3)->phoneNumber(),
                'mailing_same_as_registered' => $faker->boolean(70),
                'mailing_address' => $faker->optional(0.4)->address(),
                'mailing_phone' => $faker->optional(0.3)->phoneNumber(),
                'residence_duration' => $faker->optional(0.4)->randomElement(['未滿1年', '1-3年', '3-5年', '5-10年', '10年以上']),
                'residence_owner' => $faker->optional(0.4)->randomElement(['自有', '租賃', '家人', '配偶']),
                'telecom_provider' => $faker->optional(0.5)->randomElement($telecomProviders),

                // Random company information
                'company_name' => $faker->optional(0.6)->company(),
                'company_phone' => $faker->optional(0.5)->phoneNumber(),
                'company_address' => $faker->optional(0.5)->address(),
                'job_title' => $faker->optional(0.6)->randomElement($jobTitles),
                'monthly_income' => $faker->optional(0.6)->numberBetween(30000, 150000),
                'labor_insurance_transfer' => $faker->optional(0.4)->boolean(),
                'current_job_duration' => $faker->optional(0.5)->randomElement(['未滿1年', '1-3年', '3-5年', '5-10年', '10年以上']),

                // Random emergency contacts
                'emergency_contact_1_name' => $faker->optional(0.7)->name(),
                'emergency_contact_1_relationship' => $faker->optional(0.7)->randomElement(['父母', '配偶', '兄弟姊妹', '朋友', '同事']),
                'emergency_contact_1_phone' => $faker->optional(0.7)->phoneNumber(),
                'emergency_contact_1_available_time' => $faker->optional(0.5)->randomElement($contactTimes),
                'emergency_contact_1_confidential' => $faker->boolean(50), // 50% true, 50% false

                'emergency_contact_2_name' => $faker->optional(0.5)->name(),
                'emergency_contact_2_relationship' => $faker->optional(0.5)->randomElement(['父母', '配偶', '兄弟姊妹', '朋友', '同事']),
                'emergency_contact_2_phone' => $faker->optional(0.5)->phoneNumber(),
                'emergency_contact_2_available_time' => $faker->optional(0.4)->randomElement($contactTimes),
                'emergency_contact_2_confidential' => $faker->boolean(40), // 40% true, 60% false

                'referrer' => $faker->optional(0.3)->name(),
            ]);

            // Point 4: Create notification for the new WP lead
            $notification = Notification::create([
                'type' => 'wp_lead',
                'title' => '網路進線通知',
                'message' => "網路進線有新客戶，請業務儘快聯絡！客戶：{$name}，電話：{$phone}",
                'user_id' => null, // Broadcast to all users
                'lead_id' => $lead->id,
                'is_read' => false,
                'priority' => 'high',
                'data' => [
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                    'customer_email' => $email,
                    'website_domain' => parse_url($source, PHP_URL_HOST),
                    'lead_id' => $lead->id,
                    'customer_id' => $existingCustomer->id,
                    'generated_by_test_api' => true,
                ],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '隨機網路進線資料已成功建立',
                'data' => [
                    'lead' => [
                        'id' => $lead->id,
                        'name' => $lead->name,
                        'phone' => $lead->phone,
                        'email' => $lead->email,
                        'line_id' => $lead->line_id,
                        'source' => $lead->source,
                        'contact_time' => $lead->contact_time,
                        'created_at' => $lead->created_at,
                    ],
                    'customer' => [
                        'id' => $existingCustomer->id,
                        'name' => $existingCustomer->name,
                        'phone' => $existingCustomer->phone,
                    ],
                    'notification' => [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'priority' => $notification->priority,
                    ],
                    'random_fields' => [
                        'capital_need' => $capitalNeed,
                        'loan_type' => $loanType,
                        'region' => $region,
                        'education_level' => $educationLevel,
                        'job_title' => $jobTitle,
                    ],
                ],
                'test_info' => [
                    'generated_at' => now()->format('Y-m-d H:i:s'),
                    'source_ip' => $request->ip(),
                    'api_endpoint' => '/api/test/generate-wp-lead',
                ],
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => '建立隨機資料時發生錯誤',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }
}
