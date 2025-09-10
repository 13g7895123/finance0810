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
  -H "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "姓名=我你媽" \
  --data-urlencode "手機號碼=0908121645" \
  --data-urlencode "方便聯絡時間=上午9:00-12:00" \
  --data-urlencode "資金需求=30萬以下" \
  --data-urlencode "貸款需求=二胎房貸" \
  --data-urlencode "LINE_ID=as1234" \
  --data-urlencode "房屋區域=臺北市" \
  --data-urlencode "房屋地址=測試地址" \
  --data-urlencode "日期=12 8 月, 2025" \
  --data-urlencode "時間=12:32 上午" \
  --data-urlencode "頁面 URL=https://easypay-life.com.tw/contact/"
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
            'events_data' => [
                'field_names' => array_keys($request->all()),
                'field_count' => count($request->all())
            ]
        ]);

        $executionLog->addExecutionStep('webhook_received', [
            'field_names' => array_keys($request->all()),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        // 記錄接收到的原始資料
        Log::info('Point64 - WordPress Webhook除錯記錄', [
            'execution_id' => $executionLog->execution_id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'raw_data_keys' => array_keys($request->all()),
            'raw_data' => $request->all()
        ]);

        try {
            // 1) 取出原始表單資料
            $rawFormData = $request->all();

            // 2) 從頁面URL提取網站域名
            $pageUrl = $rawFormData['頁面 URL'] ?? $rawFormData['page_url'] ?? null;
            $websiteDomain = null;
            
            if ($pageUrl) {
                $websiteDomain = $this->fieldMapper->extractDomainFromUrl($pageUrl);
            }
            
            if (!$websiteDomain) {
                Log::warning('Point61 - 無法從表單資料中確定網站域名', ['raw_data' => $rawFormData]);
                // 使用預設域名或從 HTTP_HOST 取得
                $websiteDomain = $request->getHost() ?: 'default';
            }

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
                'source' => $pageUrl,
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
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            $executionLog->addExecutionStep('database_transaction_rollback', [
                'error' => $e->getMessage()
            ], 'failed');
            
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
        } catch (\Throwable $outerException) {
            // Point 64: 處理最外層異常（在執行記錄建立之前的錯誤）
            Log::error('Point64 - WordPress Webhook執行失敗', [
                'error' => $outerException->getMessage(),
                'trace' => $outerException->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'Webhook processing failed',
                'error' => $outerException->getMessage(),
            ], 500);
        }
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
}
