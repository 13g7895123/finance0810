<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\CustomerLead;
use App\Models\CustomerIdentifier;
use App\Models\CustomerActivity;

class WebhookController extends Controller
{
    public function wp(Request $request)
    {
        /**
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
        // 1) 取出表單欄位（中文鍵名），以彈性 mapping
        $payload = $request->all();

        // 將未識別鍵映射到自訂欄位（lead 層級），若已有對應 custom_field 則寫值
        // 約定：custom_fields.entity_type = 'lead' 且 key 與 webhook 鍵一致（或之後可做 mapping 表）
        $customFieldInput = $payload;

        $name = $payload['姓名'] ?? null;
        $phone = $payload['手機號碼'] ?? null;
        $contactTime = $payload['方便聯絡時間'] ?? null;
        $capitalNeed = $payload['資金需求'] ?? null;
        $loanNeed = $payload['貸款需求'] ?? null;
        $lineId = $payload['LINE_ID'] ?? null;
        $region = $payload['房屋區域'] ?? null;
        $address = $payload['房屋地址'] ?? null;
        $date = $payload['日期'] ?? null;
        $time = $payload['時間'] ?? null;
        $pageUrl = $payload['頁面 URL'] ?? null;
        $userAgent = $payload['使用者代理'] ?? $request->userAgent();
        $remoteIp = $payload['遠端 IP'] ?? $request->ip();
        $poweredBy = $payload['Powered by'] ?? null;
        $formId = $payload['form_id'] ?? null;
        $formName = $payload['form_name'] ?? null;
        $email = $payload['Email'] ?? ($payload['email'] ?? null);

        // 2) 黑名單偵測：同一 IP 但姓名不同，或同一 IP + LINE_ID/手機號碼但姓名不同
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

        // 3) 以 LINE/手機/Email 決定是否為同一人 → 綁定 identifiers
        $identifierValues = [];
        if ($lineId) $identifierValues['line'] = trim($lineId);
        if ($phone) $identifierValues['phone'] = preg_replace('/\D+/', '', $phone);
        if ($email) $identifierValues['email'] = strtolower(trim($email));

        DB::beginTransaction();
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

            if (!$existingCustomer) {
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
                        'form' => [ 'id' => $formId, 'name' => $formName ],
                        'submit_datetime' => trim(($date ? $date.' ' : '').($time ?? '')),
                        'powered_by' => $poweredBy,
                    ],
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
            }

            // 綁定識別子（避免重覆，使用唯一索引）
            foreach ($identifierValues as $type => $value) {
                CustomerIdentifier::firstOrCreate([
                    'type' => $type,
                    'value' => $value,
                ], [
                    'customer_id' => $existingCustomer->id,
                ]);
            }

            // 建立 lead 紀錄
            // 更新客戶快照 IP（最後一次來源）
            $existingCustomer->update(['last_ip_address' => $remoteIp]);

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

            // 寫入對應的自訂欄位（lead）
            try {
                $definedFields = \App\Models\CustomField::where('entity_type', 'lead')->pluck('id', 'key');
                foreach ($customFieldInput as $k => $v) {
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
            } catch (\Throwable $e) {
                // 若自訂欄位寫入失敗，不阻塞主流程
            }

            // 如為疑似黑名單，寫活動
            if ($isSuspectedBlacklist) {
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

            return response()->json([
                'message' => 'Webhook processed',
                'customer_id' => $existingCustomer->id,
                'lead_id' => $lead->id,
                'suspected_blacklist' => $isSuspectedBlacklist,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Webhook failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
