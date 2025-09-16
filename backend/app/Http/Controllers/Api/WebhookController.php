<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\CustomerIdentifier;
use App\Models\CustomerActivity;
use App\Models\WebhookExecutionLog;
use App\Services\FormFieldMapper;

class WebhookController extends Controller
{
    protected FormFieldMapper $fieldMapper;

    public function __construct(FormFieldMapper $fieldMapper)
    {
        $this->fieldMapper = $fieldMapper;
    }

    /**
     * Point 64: 取得Webhook執行記錄列表（除錯用）
     */
    public function getExecutionLogs(Request $request)
    {
        $query = WebhookExecutionLog::query();

        // 篩選條件
        if ($request->filled('webhook_type')) {
            $query->where('webhook_type', $request->webhook_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('execution_id')) {
            $query->where('execution_id', 'like', '%' . $request->execution_id . '%');
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        if ($request->filled('date_from')) {
            $query->where('started_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('started_at', '<=', $request->date_to . ' 23:59:59');
        }

        // 排序
        $query->orderBy('started_at', 'desc');

        // 分頁
        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json($logs);
    }

    /**
     * Point 64: 取得單一Webhook執行記錄詳細資料（除錯用）
     */
    public function getExecutionLogDetail($executionId)
    {
        $log = WebhookExecutionLog::where('execution_id', $executionId)->first();

        if (!$log) {
            return response()->json(['message' => 'Execution log not found'], 404);
        }

        return response()->json($log);
    }

    public function wp(Request $request)
    {
        /**
         * Point 61: WordPress表單webhook處理，支援動態欄位對應
         * Point 64: 加入除錯記錄功能
         * 
         * mock curl -X POST "http://localhost:8000/api/webhook/wp" \        
         * -H "Content-Type: application/x-www-form-urlencoded" \
         * --data-urlencode "姓名=我你媽" \
         * --data-urlencode "手機號碼=0908121645" \
         * --data-urlencode "方便聯絡時間=上午9:00-12:00" \
         * --data-urlencode "資金需求=30萬以下" \
         * --data-urlencode "貸款需求=二胎房貸" \
         * --data-urlencode "LINE_ID=as1234" \
         * --data-urlencode "房屋區域=臺北市" \
         * --data-urlencode "房屋地址=測試地址" \
         * --data-urlencode "日期=12 8 月, 2025" \
         * --data-urlencode "時間=12:32 上午" \
         * --data-urlencode "頁面 URL=https://easypay-life.com.tw/contact/"
        */

        // Point 64: 建立除錯記錄
        $executionLog = WebhookExecutionLog::create([
            'execution_id' => uniqid('wp_', true),
            'webhook_type' => 'wp',
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'request_headers' => $request->headers->all(),
            'request_body' => json_encode($request->all(), JSON_UNESCAPED_UNICODE),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'processing',
            'started_at' => now(),
            'events_data' => json_decode(json_encode([
                'field_names' => array_keys($request->all()),
                'field_count' => count($request->all())
            ], JSON_UNESCAPED_UNICODE), true)
        ]);

        $executionLog->addExecutionStep('webhook_received', [
            'field_names' => array_keys($request->all()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // Point 1: 記錄完整POST資料到wp.log
        Log::channel('wp')->info('WordPress Webhook - 接收資料', [
            'execution_id' => $executionLog->execution_id,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'post_data' => $request->all(),
            'raw_body' => $request->getContent(),
            'field_count' => count($request->all())
        ]);

        // Point 5: 檢測並處理結構化表單資料格式
        $rawFormData = $request->all();

        // 處理 JSON 請求
        if (empty($rawFormData) && $request->isJson()) {
            $jsonContent = $request->getContent();
            $decodedData = json_decode($jsonContent, true);

            if ($decodedData !== null) {
                $rawFormData = $decodedData;
                Log::channel('wp')->info('WordPress Webhook - 檢測到JSON格式', [
                    'execution_id' => $executionLog->execution_id,
                    'json_data_keys' => array_keys($rawFormData),
                    'json_valid' => true
                ]);
            } else {
                Log::channel('wp')->error('WordPress Webhook - JSON解析失敗', [
                    'execution_id' => $executionLog->execution_id,
                    'json_error' => json_last_error_msg(),
                    'raw_content_preview' => substr($jsonContent, 0, 200)
                ]);
            }
        }

        $isStructuredFormat = $this->isStructuredFormat($rawFormData);

        Log::channel('wp')->info('WordPress Webhook - 資料格式檢測', [
            'execution_id' => $executionLog->execution_id,
            'is_structured_format' => $isStructuredFormat,
            'has_fields_key' => isset($rawFormData['fields']),
            'has_form_key' => isset($rawFormData['form']),
            'data_keys' => array_keys($rawFormData),
            'is_json_request' => $request->isJson()
        ]);

        // 如果是結構化格式，先進行資料轉換
        if ($isStructuredFormat) {
            $rawFormData = $this->extractStructuredData($rawFormData, $executionLog);
            Log::channel('wp')->info('WordPress Webhook - 結構化資料轉換完成', [
                'execution_id' => $executionLog->execution_id,
                'extracted_fields' => array_keys($rawFormData),
                'extracted_data' => $rawFormData
            ]);
        }


        try {
            // 1) 取出表單資料（已經過結構化格式處理）
            // $rawFormData 已在上面處理過

            // 2) 從頁面URL和User-Agent提取網站域名
            $pageUrl = $rawFormData['頁面 URL'] ?? $rawFormData['page_url'] ?? null;
            $userAgent = $request->userAgent();
            $websiteDomain = null;
            $extractionMethod = null;

            // Point 6 & 7: 詳細記錄網站URL提取過程，協助除錯
            Log::channel('wp')->info('WordPress Webhook - 網站URL提取開始', [
                'execution_id' => $executionLog->execution_id,
                'available_url_fields' => [
                    '頁面 URL' => $rawFormData['頁面 URL'] ?? null,
                    'page_url' => $rawFormData['page_url'] ?? null,
                    'url' => $rawFormData['url'] ?? null,
                    '頁面_URL' => $rawFormData['頁面_URL'] ?? null,
                ],
                'selected_page_url' => $pageUrl,
                'user_agent' => $userAgent,
                'request_host' => $request->getHost(),
                'request_url' => $request->fullUrl(),
                'user_agent_from_form' => $rawFormData['使用者代理'] ?? $rawFormData['user_agent'] ?? null
            ]);

            // Point 7: 三階段域名提取策略除錯日誌
            Log::channel('wp')->info('WordPress Webhook - 開始三階段域名提取策略', [
                'execution_id' => $executionLog->execution_id,
                'page_url_available' => !empty($pageUrl),
                'page_url_value' => $pageUrl,
                'user_agent_available' => !empty($userAgent),
                'user_agent_value' => $userAgent,
                'request_host' => $request->getHost(),
                'is_mrmoney_request' => str_contains($userAgent ?? '', 'mrmoney.com.tw'),
                'strategy' => 'three_tier_domain_extraction'
            ]);

            // 方法1: 優先從表單的頁面URL提取域名
            if ($pageUrl) {
                Log::channel('wp')->info('WordPress Webhook - 嘗試方法1: 頁面URL域名提取', [
                    'execution_id' => $executionLog->execution_id,
                    'page_url' => $pageUrl,
                    'method' => 'page_url_extraction'
                ]);

                $websiteDomain = $this->fieldMapper->extractDomainFromUrl($pageUrl);
                if ($websiteDomain) {
                    $extractionMethod = 'from_page_url';

                    // Point 6: 記錄域名提取結果
                    Log::channel('wp')->info('WordPress Webhook - 域名提取成功 (頁面URL)', [
                        'execution_id' => $executionLog->execution_id,
                        'original_page_url' => $pageUrl,
                        'extracted_domain' => $websiteDomain,
                        'extraction_method' => $extractionMethod,
                        'is_mrmoney' => $websiteDomain === 'mrmoney.com.tw',
                        'method_1_success' => true
                    ]);
                } else {
                    Log::channel('wp')->warning('WordPress Webhook - 方法1失敗: 無法從頁面URL提取域名', [
                        'execution_id' => $executionLog->execution_id,
                        'page_url' => $pageUrl,
                        'extraction_failed' => true,
                        'fallback_to_method_2' => true
                    ]);
                }
            } else {
                Log::channel('wp')->info('WordPress Webhook - 跳過方法1: 無頁面URL', [
                    'execution_id' => $executionLog->execution_id,
                    'page_url' => null,
                    'skip_method_1' => true
                ]);
            }

            // 方法2: Point 7 - 如果頁面URL無法提取域名，嘗試從User-Agent提取
            if (!$websiteDomain && $userAgent) {
                Log::channel('wp')->info('WordPress Webhook - 嘗試方法2: User-Agent域名提取', [
                    'execution_id' => $executionLog->execution_id,
                    'user_agent' => $userAgent,
                    'method' => 'user_agent_extraction',
                    'contains_mrmoney' => str_contains($userAgent, 'mrmoney.com.tw')
                ]);

                $websiteDomain = $this->extractDomainFromUserAgent($userAgent);
                if ($websiteDomain) {
                    $extractionMethod = 'from_user_agent';

                    // Point 7: 記錄User-Agent域名提取結果
                    Log::channel('wp')->info('WordPress Webhook - 域名提取成功 (User-Agent)', [
                        'execution_id' => $executionLog->execution_id,
                        'user_agent' => $userAgent,
                        'extracted_domain' => $websiteDomain,
                        'extraction_method' => $extractionMethod,
                        'is_mrmoney' => $websiteDomain === 'mrmoney.com.tw',
                        'method_2_success' => true
                    ]);
                } else {
                    Log::channel('wp')->warning('WordPress Webhook - 方法2失敗: 無法從User-Agent提取域名', [
                        'execution_id' => $executionLog->execution_id,
                        'user_agent' => $userAgent,
                        'extraction_failed' => true,
                        'fallback_to_method_3' => true
                    ]);
                }
            } else {
                Log::channel('wp')->info('WordPress Webhook - 跳過方法2', [
                    'execution_id' => $executionLog->execution_id,
                    'domain_already_found' => !empty($websiteDomain),
                    'user_agent_available' => !empty($userAgent),
                    'current_domain' => $websiteDomain,
                    'skip_method_2' => true
                ]);
            }

            // 方法3: 最後回退到請求主機
            if (!$websiteDomain) {
                Log::channel('wp')->warning('WordPress Webhook - 進入方法3: 使用回退域名', [
                    'execution_id' => $executionLog->execution_id,
                    'method_1_failed' => true,
                    'method_2_failed' => true,
                    'using_fallback' => true
                ]);

                $fallbackDomain = $request->getHost() ?: 'default';
                $extractionMethod = 'fallback_host';

                // Point 6 & 7: 記錄回退域名的詳細資訊
                Log::channel('wp')->warning('WordPress Webhook - 使用回退域名', [
                    'execution_id' => $executionLog->execution_id,
                    'reason' => '無法從表單資料和User-Agent中提取網站域名',
                    'page_url_found' => !empty($pageUrl),
                    'page_url_value' => $pageUrl,
                    'user_agent_checked' => !empty($userAgent),
                    'user_agent_value' => $userAgent,
                    'fallback_domain' => $fallbackDomain,
                    'extraction_method' => $extractionMethod,
                    'all_form_keys' => array_keys($rawFormData),
                    'is_mrmoney_ua' => str_contains($userAgent ?? '', 'mrmoney.com.tw'),
                    'method_3_fallback' => true
                ]);

                $websiteDomain = $fallbackDomain;
            }

            // Point 7: 最終域名提取結果總結
            Log::channel('wp')->info('WordPress Webhook - 域名提取策略完成', [
                'execution_id' => $executionLog->execution_id,
                'final_domain' => $websiteDomain,
                'extraction_method' => $extractionMethod,
                'is_mrmoney_domain' => $websiteDomain === 'mrmoney.com.tw',
                'strategy_completed' => true,
                'next_step' => 'field_mapping'
            ]);

            // 3) 使用FormFieldMapper進行欄位對應
            $executionLog->addExecutionStep('field_mapping_start', [
                'website_domain' => $websiteDomain,
                'raw_field_count' => count($rawFormData)
            ]);

            try {
                $mappedData = $this->fieldMapper->mapFields($websiteDomain, $rawFormData);
                
                $executionLog->addExecutionStep('field_mapping_success', [
                    'mapped_fields' => array_keys($mappedData),
                    'unmapped_fields' => array_keys($mappedData['_unmapped_fields'] ?? []),
                    'mapped_count' => count(array_filter(array_keys($mappedData), fn($key) => !str_starts_with($key, '_')))
                ]);
                
            } catch (\Exception $fieldMappingException) {
                // 如果欄位對應失敗，記錄錯誤並回退到預設對應
                Log::error('Point61 - 欄位對應失敗，回退到預設對應', [
                    'website_domain' => $websiteDomain,
                    'error' => $fieldMappingException->getMessage(),
                    'trace' => $fieldMappingException->getTraceAsString()
                ]);
                
                $executionLog->addExecutionStep('field_mapping_failed', [
                    'error' => $fieldMappingException->getMessage(),
                    'fallback_to_default' => true
                ], 'failed');
                
                // 使用預設硬編碼對應作為回退
                $mappedData = $this->getDefaultFieldMapping($rawFormData);
                
                $executionLog->addExecutionStep('default_mapping_applied', [
                    'mapped_fields' => array_keys($mappedData)
                ]);
            }
            
            // 4) 提取標準化的欄位值
            $name = $mappedData['name'] ?? null;
            $phone = $mappedData['phone'] ?? null;
            $email = $mappedData['email'] ?? null;
            $lineId = $mappedData['line_id'] ?? null;
            $contactTime = $mappedData['contact_time'] ?? null;
            $capitalNeed = $mappedData['capital_need'] ?? null;
            $loanNeed = $mappedData['loan_need'] ?? null;
            $region = $mappedData['region'] ?? null;
            $address = $mappedData['address'] ?? null;
            $date = $mappedData['date'] ?? null;
            $time = $mappedData['time'] ?? null;
            
            // 5) 系統欄位
            $userAgent = $request->userAgent();
            $remoteIp = $request->ip();
            $pageUrl = $mappedData['page_url'] ?? $pageUrl;
            
            // 6) 保存完整的payload資料
            $payload = $mappedData['_original_payload'] ?? $rawFormData;
            $unmappedFields = $mappedData['_unmapped_fields'] ?? [];
            
            // 記錄映射結果
            Log::info('Point61 - 欄位映射完成', [
                'website_domain' => $websiteDomain,
                'mapped_fields' => array_filter([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'line_id' => $lineId
                ]),
                'unmapped_count' => count($unmappedFields)
            ]);

            // 7) 黑名單偵測：同一 IP 但姓名不同，或同一 IP + LINE_ID/手機號碼但姓名不同
            $executionLog->addExecutionStep('blacklist_detection_start', [
                'ip_address' => $remoteIp,
                'current_name' => $name,
                'line_id' => $lineId,
                'phone' => $phone
            ]);

            $ipDifferentNames = CustomerLead::where('ip_address', $remoteIp)
            ->whereNotNull('name')
            ->when($name, fn($q) => $q->where('name', '!=', $name))
            ->exists();

        // 新增條件：同一 IP + LINE_ID 或手機號碼相同但姓名不同
        $ipLineOrPhoneDifferentName = false;
        if ($lineId) {
            $ipLineOrPhoneDifferentName = CustomerLead::where('ip_address', $remoteIp)
                ->whereNotNull('name')
                ->where('line_id', $lineId)
                ->when($name, fn($q) => $q->where('name', '!=', $name))
                ->exists();
        }
        if (!$ipLineOrPhoneDifferentName && $phone) {
            $normalizedPhone = preg_replace('/\D+/', '', $phone);
            // 先查詢同 IP 下有 phone 的紀錄，再用 PHP 過濾
            $leads = CustomerLead::where('ip_address', $remoteIp)
                ->whereNotNull('name')
                ->whereNotNull('phone')
                ->when($name, fn($q) => $q->where('name', '!=', $name))
                ->get();
            foreach ($leads as $lead) {
                $dbPhone = preg_replace('/\D+/', '', $lead->phone);
                if ($dbPhone === $normalizedPhone) {
                    $ipLineOrPhoneDifferentName = true;
                    break;
                }
            }
        }

        $isSuspectedBlacklist = $ipDifferentNames || $ipLineOrPhoneDifferentName;
        $isSuspectedBlacklist = false; // TODO: 先關閉黑名單偵測，等後續調整
        if ($ipDifferentNames) {
            $suspectedReason = '同一 IP 多姓名提交（疑似黑名單）';
        } elseif ($ipLineOrPhoneDifferentName) {
            $suspectedReason = '同一 IP 且 LINE_ID 或手機號碼相同但姓名不同（疑似黑名單）';
        } else {
            $suspectedReason = null;
        }

        $executionLog->addExecutionStep('blacklist_detection_completed', [
            'is_suspected_blacklist' => $isSuspectedBlacklist,
            'suspected_reason' => $suspectedReason,
            'ip_different_names' => $ipDifferentNames,
            'ip_line_phone_different_name' => $ipLineOrPhoneDifferentName
        ]);

        // 3) 以 LINE/手機/Email 決定是否為同一人 → 綁定 identifiers
        $identifierValues = [];
        if ($lineId) $identifierValues['line'] = trim($lineId);
        if ($phone) $identifierValues['phone'] = preg_replace('/\D+/', '', $phone);
        if ($email) $identifierValues['email'] = strtolower(trim($email));

        $executionLog->addExecutionStep('identifier_preparation', [
            'identifier_values' => $identifierValues,
            'identifier_count' => count($identifierValues)
        ]);

        // Point 5: 啟用 SQL 查詢日誌記錄
        DB::enableQueryLog();

        DB::beginTransaction();
        $executionLog->addExecutionStep('database_transaction_start');

        try {
            // 找出是否已有客戶（任一識別符合即可）
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

            $executionLog->addExecutionStep('customer_lookup', [
                'existing_customer_found' => $existingCustomer ? true : false,
                'customer_id' => $existingCustomer?->id,
                'search_identifiers' => array_keys($identifierValues)
            ]);

            if (!$existingCustomer) {
                $executionLog->addExecutionStep('customer_creation_start', [
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'region' => $region
                ]);
                // 建立新客戶（最小必要資料）
                $existingCustomer = Customer::create([
                    'name' => $name ?? '未填寫',
                    'phone' => $phone ?? null,
                    'email' => $email ?? null,
                    'region' => $region ?? null,
                    'channel' => 'web_form',
                    'website_source' => $pageUrl ? parse_url($pageUrl, PHP_URL_HOST) : null,
                    'status' => Customer::STATUS_NEW,
                    'address' => $address ?? null,
                    'notes' => $contactTime ? ("方便聯絡時間：$contactTime") : null,
                    'source_data' => [
                        'page_url' => $pageUrl,
                        'address' => $address,
                        'loan_need' => $loanNeed,
                        'capital_need' => $capitalNeed,
                        'website_domain' => $websiteDomain,
                        'submit_datetime' => trim(($date ? $date.' ' : '').($time ?? '')),
                        'raw_payload' => $payload,
                    ],
                ]);

                $executionLog->addExecutionStep('customer_created', [
                    'customer_id' => $existingCustomer->id,
                    'customer_name' => $existingCustomer->name,
                    'customer_phone' => $existingCustomer->phone,
                    'customer_email' => $existingCustomer->email
                ]);

                // Point 6: 詳細記錄客戶創建時的網站資訊
                Log::channel('wp')->info('WordPress Webhook - 客戶建立完成，網站資訊記錄', [
                    'execution_id' => $executionLog->execution_id,
                    'customer_id' => $existingCustomer->id,
                    'customer_name' => $existingCustomer->name,
                    'website_info' => [
                        'original_page_url' => $pageUrl,
                        'extracted_website_domain' => $websiteDomain,
                        'customer_website_source' => $existingCustomer->website_source,
                        'stored_page_url' => $existingCustomer->source_data['page_url'] ?? null,
                        'stored_website_domain' => $existingCustomer->source_data['website_domain'] ?? null,
                        'submit_datetime' => $existingCustomer->source_data['submit_datetime'] ?? null,
                    ],
                    'ip_info' => [
                        'remote_ip' => $remoteIp,
                        'user_agent' => $userAgent,
                        'request_host' => $request->getHost(),
                    ]
                ]);

                // 建立活動記錄：created
                CustomerActivity::create([
                    'customer_id' => $existingCustomer->id,
                    'user_id' => null,
                    'activity_type' => CustomerActivity::TYPE_CREATED,
                    'description' => '由 WP 表單建立客戶',
                    'old_data' => null,
                    'new_data' => $existingCustomer->toArray(),
                    'ip_address' => $remoteIp,
                    'user_agent' => $userAgent,
                ]);
            } else {
                $executionLog->addExecutionStep('customer_update_start', [
                    'existing_customer_id' => $existingCustomer->id,
                    'existing_customer_name' => $existingCustomer->name
                ]);
                
                // 既有客戶：補充欄位（空才補）
                $updates = [];
                foreach ([
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'region' => $region,
                ] as $field => $val) {
                    if ($val && empty($existingCustomer->{$field})) {
                        $updates[$field] = $val;
                    }
                }
                if (!empty($updates)) {
                    $old = $existingCustomer->only(array_keys($updates));
                    $existingCustomer->fill($updates)->save();
                    CustomerActivity::create([
                        'customer_id' => $existingCustomer->id,
                        'user_id' => null,
                        'activity_type' => CustomerActivity::TYPE_UPDATED,
                        'description' => 'WP 表單補充客戶資料',
                        'old_data' => $old,
                        'new_data' => $updates,
                        'ip_address' => $remoteIp,
                        'user_agent' => $userAgent,
                    ]);
                }
                
                $executionLog->addExecutionStep('customer_update_completed', [
                    'updates_applied' => !empty($updates),
                    'updated_fields' => array_keys($updates),
                    'customer_id' => $existingCustomer->id
                ]);
            }

            // 綁定識別子（避免重覆，使用唯一索引）
            $executionLog->addExecutionStep('identifier_binding_start', [
                'identifiers_to_bind' => $identifierValues,
                'customer_id' => $existingCustomer->id
            ]);
            
            foreach ($identifierValues as $type => $value) {
                CustomerIdentifier::firstOrCreate([
                    'type' => $type,
                    'value' => $value,
                ], [
                    'customer_id' => $existingCustomer->id,
                ]);
            }
            
            $executionLog->addExecutionStep('identifier_binding_completed', [
                'bound_identifiers_count' => count($identifierValues),
                'customer_id' => $existingCustomer->id
            ]);

            // 建立 lead 紀錄
            // 更新客戶快照 IP（最後一次來源）
            $existingCustomer->update(['last_ip_address' => $remoteIp]);

            $executionLog->addExecutionStep('lead_creation_start', [
                'customer_id' => $existingCustomer->id,
                'channel' => 'wp_form',
                'is_suspected_blacklist' => $isSuspectedBlacklist
            ]);

            $lead = CustomerLead::create([
                'customer_id' => $existingCustomer->id,
                'assigned_to' => $existingCustomer->assigned_to, // 若客戶已有承辦則沿用，否則為 null
                'channel' => 'wp_form',
                'source' => "https://{$websiteDomain}/",
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'line_id' => $lineId,
                'ip_address' => $remoteIp,
                'user_agent' => $userAgent,
                'payload' => $payload,
                'is_suspected_blacklist' => $isSuspectedBlacklist,
                'suspected_reason' => $suspectedReason,
                'status' => $isSuspectedBlacklist ? 'blacklist' : 'pending',
            ]);

            $executionLog->addExecutionStep('lead_created', [
                'lead_id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'channel' => $lead->channel,
                'status' => $lead->status,
                'is_suspected_blacklist' => $lead->is_suspected_blacklist
            ]);

            // 寫入對應的自訂欄位（lead）- 使用unmapped fields作為custom fields
            try {
                $executionLog->addExecutionStep('custom_fields_processing_start', [
                    'unmapped_fields_count' => count($unmappedFields)
                ]);
                
                $definedFields = \App\Models\CustomField::where('entity_type', 'lead')->pluck('id', 'key');
                foreach ($unmappedFields as $k => $v) {
                    if (isset($definedFields[$k])) {
                        \App\Models\CustomFieldValue::updateOrCreate([
                            'entity_type' => 'lead',
                            'entity_id' => $lead->id,
                            'field_id' => $definedFields[$k],
                        ], [
                            'value' => is_array($v) ? json_encode($v) : (string) $v,
                            'updated_by' => null,
                        ]);
                    }
                }
                
                $executionLog->addExecutionStep('custom_fields_processed', [
                    'processed_fields' => count(array_intersect_key($unmappedFields, $definedFields)),
                    'total_defined_fields' => count($definedFields)
                ]);
                
            } catch (\Throwable $e) {
                $executionLog->addExecutionStep('custom_fields_processing_failed', [
                    'error' => $e->getMessage()
                ], 'failed');
                // 若自訂欄位寫入失敗，不阻塞主流程
            }

            // 如為疑似黑名單，寫活動
            if ($isSuspectedBlacklist) {
                $executionLog->addExecutionStep('blacklist_activity_creation', [
                    'customer_id' => $existingCustomer->id,
                    'suspected_reason' => $suspectedReason,
                    'lead_id' => $lead->id
                ]);
                
                CustomerActivity::create([
                    'customer_id' => $existingCustomer->id,
                    'user_id' => null,
                    'activity_type' => CustomerActivity::TYPE_SUSPECTED_BLACKLIST,
                    'description' => $suspectedReason,
                    'old_data' => null,
                    'new_data' => [ 'lead_id' => $lead->id ],
                    'ip_address' => $remoteIp,
                    'user_agent' => $userAgent,
                ]);
            }

            DB::commit();
            $executionLog->addExecutionStep('database_transaction_committed');

            // Point 5: 記錄所有執行的 SQL 查詢
            $queries = DB::getQueryLog();
            // Log::channel('wp')->info('WordPress Webhook - SQL 查詢記錄', [
            //     'execution_id' => $executionLog->execution_id,
            //     'total_queries' => count($queries),
            //     'queries' => array_map(function($query) {
            //         return [
            //             'sql' => $query['query'],
            //             'bindings' => $query['bindings'],
            //             'time' => $query['time'] . 'ms'
            //         ];
            //     }, $queries)
            // ]);

            // Point 1 & Point 6: 記錄成功處理到wp.log，包含詳細網站URL資訊
            Log::channel('wp')->info('WordPress Webhook - 處理成功', [
                'execution_id' => $executionLog->execution_id,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'customer_id' => $existingCustomer->id,
                'lead_id' => $lead->id,
                'customer_name' => $existingCustomer->name,
                'customer_phone' => $existingCustomer->phone,
                'website_domain' => $websiteDomain,
                'is_suspected_blacklist' => $isSuspectedBlacklist,
                'processing_duration' => now()->diffInSeconds($executionLog->started_at) . 's',
                'total_sql_queries' => count($queries),
                // Point 6 & 7: 詳細網站URL除錯資訊
                'website_url_summary' => [
                    'original_page_url' => $pageUrl,
                    'extracted_domain' => $websiteDomain,
                    'extraction_method' => $extractionMethod,
                    'request_host' => $request->getHost(),
                    'customer_website_source' => $existingCustomer->website_source,
                    'url_extraction_successful' => !empty($websiteDomain) && $extractionMethod !== 'fallback_host',
                    'used_fallback_domain' => $extractionMethod === 'fallback_host',
                    'used_user_agent_extraction' => $extractionMethod === 'from_user_agent'
                ],
                'ip_tracking_info' => [
                    'client_ip' => $remoteIp,
                    'forwarded_ip' => $request->header('X-Forwarded-For'),
                    'real_ip' => $request->header('X-Real-IP'),
                    'user_agent' => substr($userAgent, 0, 100) // 限制長度避免日誌過長
                ]
            ]);

            // Point 64: 標記執行完成
            $executionLog->markCompleted([
                'customer_id' => $existingCustomer->id,
                'lead_id' => $lead->id,
                'suspected_blacklist' => $isSuspectedBlacklist,
                'total_steps' => count($executionLog->execution_steps ?? [])
            ]);

            return response()->json([
                'message' => 'Webhook processed',
                'customer_id' => $existingCustomer->id,
                'lead_id' => $lead->id,
                'suspected_blacklist' => $isSuspectedBlacklist,
                'execution_id' => $executionLog->execution_id, // Point 64: 回傳執行ID供除錯用
            ], 200); // Point 6: 修改為200狀態碼
        } catch (\Throwable $e) {
            DB::rollBack();
            $executionLog->addExecutionStep('database_transaction_rollback', [
                'error' => $e->getMessage()
            ], 'failed');

            // Point 5: 記錄錯誤時的 SQL 查詢
            $queries = DB::getQueryLog();
            Log::channel('wp')->error('WordPress Webhook - 錯誤時的 SQL 查詢記錄', [
                'execution_id' => $executionLog->execution_id,
                'total_queries' => count($queries),
                'queries' => array_map(function($query) {
                    return [
                        'sql' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time' => $query['time'] . 'ms'
                    ];
                }, $queries)
            ]);

            // Point 1: 記錄處理錯誤到wp.log
            Log::channel('wp')->error('WordPress Webhook - 處理錯誤', [
                'execution_id' => $executionLog->execution_id,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'request_data' => $request->all(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'total_sql_queries' => count($queries)
            ]);

            // Point 64: 標記執行失敗
            $executionLog->markFailed($e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'message' => 'Webhook failed',
                'error' => $e->getMessage(),
                'execution_id' => $executionLog->execution_id, // Point 64: 回傳執行ID供除錯用
            ], 500);
        }
        } catch (\Throwable $outerException) {
            // Point 1: 記錄最外層異常到wp.log
            Log::channel('wp')->error('WordPress Webhook - 處理失敗', [
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'error_message' => $outerException->getMessage(),
                'error_trace' => $outerException->getTraceAsString(),
                'error_file' => $outerException->getFile(),
                'error_line' => $outerException->getLine(),
                'request_data' => $request->all(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl()
            ]);

            return response()->json([
                'message' => 'Webhook processing failed',
                'error' => $outerException->getMessage(),
            ], 500);
        }
    }

    /**
     * Point 5: 檢測是否為結構化表單資料格式
     */
    protected function isStructuredFormat(array $data): bool
    {
        // 檢查是否包含 fields 和 form 鍵，這是結構化格式的標誌
        return isset($data['fields']) && isset($data['form']) && is_array($data['fields']);
    }

    /**
     * Point 5: 從結構化資料中提取表單欄位
     */
    protected function extractStructuredData(array $structuredData, $executionLog): array
    {
        $extractedData = [];
        $fields = $structuredData['fields'] ?? [];
        $meta = $structuredData['meta'] ?? [];
        $form = $structuredData['form'] ?? [];

        $executionLog->addExecutionStep('structured_data_extraction_start', [
            'fields_count' => count($fields),
            'meta_count' => count($meta),
            'form_info' => $form
        ]);

        // 從 fields 中提取資料，使用 title 作為鍵名
        foreach ($fields as $fieldId => $fieldData) {
            if (isset($fieldData['title']) && isset($fieldData['value'])) {
                $title = $fieldData['title'];
                $value = $fieldData['value'];

                // 如果值為 null，跳過
                if ($value !== null) {
                    $extractedData[$title] = $value;
                }

                Log::channel('wp')->debug('WordPress Webhook - 欄位提取', [
                    'execution_id' => $executionLog->execution_id,
                    'field_id' => $fieldId,
                    'title' => $title,
                    'value' => $value,
                    'type' => $fieldData['type'] ?? 'unknown'
                ]);
            }
        }

        // 處理 meta 資訊
        foreach ($meta as $metaKey => $metaData) {
            if (isset($metaData['title']) && isset($metaData['value'])) {
                $title = $metaData['title'];
                $value = $metaData['value'];
                $extractedData[$title] = $value;
            }
        }

        // 加入表單資訊作為特殊欄位
        if (!empty($form['name'])) {
            $extractedData['表單名稱'] = $form['name'];
        }
        if (!empty($form['id'])) {
            $extractedData['表單ID'] = $form['id'];
        }

        $executionLog->addExecutionStep('structured_data_extraction_completed', [
            'extracted_fields_count' => count($extractedData),
            'extracted_fields' => array_keys($extractedData)
        ]);

        return $extractedData;
    }

    /**
     * Point 61: 預設欄位對應 (回退機制)
     * 當動態欄位對應失敗時使用
     */
    protected function getDefaultFieldMapping(array $rawFormData): array
    {
        // 使用原本的硬編碼對應作為回退
        $defaultMapping = [
            '姓名' => 'name',
            '手機號碼' => 'phone',
            'Email' => 'email',
            'email' => 'email',
            'LINE_ID' => 'line_id',
            '方便聯絡時間' => 'contact_time',
            '資金需求' => 'capital_need',
            '貸款需求' => 'loan_need',
            '房屋區域' => 'region',
            '房屋地址' => 'address',
            '日期' => 'date',
            '時間' => 'time',
            '頁面 URL' => 'page_url',
        ];

        $mappedData = [];
        $unmappedFields = [];

        foreach ($rawFormData as $wpFieldName => $value) {
            if (isset($defaultMapping[$wpFieldName])) {
                $systemField = $defaultMapping[$wpFieldName];
                
                // 基本的資料轉換
                switch ($systemField) {
                    case 'phone':
                        $mappedData[$systemField] = preg_replace('/\D+/', '', $value);
                        break;
                    case 'email':
                        $mappedData[$systemField] = strtolower(trim($value));
                        break;
                    default:
                        $mappedData[$systemField] = $value;
                        break;
                }
            } else {
                $unmappedFields[$wpFieldName] = $value;
            }
        }

        $mappedData['_original_payload'] = $rawFormData;
        $mappedData['_unmapped_fields'] = $unmappedFields;

        Log::info('Point61 - 使用預設欄位對應', [
            'mapped_fields' => array_keys($mappedData),
            'unmapped_count' => count($unmappedFields)
        ]);

        return $mappedData;
    }

    /**
     * Point 7: 從User-Agent提取網站域名
     * Point 7: 增強mrmoney.com.tw域名提取除錯日誌
     *
     * WordPress User-Agent格式: "WordPress/{version}; {url}"
     * 例如: "WordPress/6.8.2; https://mrmoney.com.tw"
     */
    protected function extractDomainFromUserAgent(string $userAgent): ?string
    {
        Log::channel('wp')->info('WebhookController - 開始User-Agent域名提取', [
            'user_agent' => $userAgent,
            'contains_wordpress' => str_contains($userAgent, 'WordPress/'),
            'method' => 'extractDomainFromUserAgent'
        ]);

        // 檢查是否為WordPress User-Agent格式
        if (!str_contains($userAgent, 'WordPress/')) {
            Log::channel('wp')->warning('WebhookController - User-Agent不包含WordPress格式', [
                'user_agent' => $userAgent,
                'expected_format' => 'WordPress/{version}; {url}'
            ]);
            return null;
        }

        // 嘗試匹配 WordPress/{version}; {url} 格式
        if (preg_match('/WordPress\/[^;]+;\s*(.+)$/', $userAgent, $matches)) {
            $url = trim($matches[1]);

            Log::channel('wp')->info('WebhookController - 成功匹配WordPress格式', [
                'user_agent' => $userAgent,
                'extracted_url' => $url,
                'regex_matches' => $matches
            ]);

            // 如果URL不包含協議，添加https://
            if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                $url = 'https://' . $url;
                Log::channel('wp')->debug('WebhookController - 自動添加https協議', [
                    'original_url' => trim($matches[1]),
                    'modified_url' => $url
                ]);
            }

            // 使用parse_url提取域名
            $parsed = parse_url($url);
            Log::channel('wp')->info('WebhookController - URL解析結果', [
                'url' => $url,
                'parsed_result' => $parsed,
                'has_host' => isset($parsed['host']),
                'extracted_host' => $parsed['host'] ?? null
            ]);

            if ($parsed && isset($parsed['host'])) {
                $extractedDomain = $parsed['host'];
                Log::channel('wp')->info('WebhookController - User-Agent域名提取成功', [
                    'user_agent' => $userAgent,
                    'extracted_domain' => $extractedDomain,
                    'is_mrmoney' => $extractedDomain === 'mrmoney.com.tw',
                    'extraction_successful' => true
                ]);
                return $extractedDomain;
            } else {
                Log::channel('wp')->warning('WebhookController - URL解析失敗', [
                    'url' => $url,
                    'parsed_result' => $parsed,
                    'parse_url_error' => 'parse_url failed or no host found'
                ]);
            }
        } else {
            Log::channel('wp')->warning('WebhookController - WordPress格式匹配失敗', [
                'user_agent' => $userAgent,
                'regex_pattern' => '/WordPress\/[^;]+;\s*(.+)$/',
                'match_failed' => true
            ]);
        }

        Log::channel('wp')->warning('WebhookController - User-Agent域名提取失敗', [
            'user_agent' => $userAgent,
            'extraction_failed' => true
        ]);
        return null;
    }
}

