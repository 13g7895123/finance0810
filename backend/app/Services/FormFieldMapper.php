<?php

namespace App\Services;

use App\Models\Website;
use App\Models\WebsiteFieldMapping;
use Illuminate\Support\Facades\Log;

/**
 * Point 61: 表單欄位對應服務
 * 
 * 負責處理WordPress表單欄位與系統標準欄位之間的對應關係
 */
class FormFieldMapper
{
    /**
     * 根據網站域名映射表單欄位
     *
     * @param string $websiteDomain 網站域名
     * @param array $rawFormData 原始表單資料
     * @return array 映射後的標準化資料
     */
    public function mapFields(string $websiteDomain, array $rawFormData): array
    {
        // Point 7: 詳細記錄網站資料庫查詢過程，協助生產環境除錯
        Log::channel('wp')->info('FormFieldMapper - 開始網站資料庫查詢', [
            'website_domain' => $websiteDomain,
            'lookup_query' => "SELECT * FROM websites WHERE domain = '{$websiteDomain}'",
            'raw_form_data_keys' => array_keys($rawFormData)
        ]);

        // 1. 查找網站記錄 - Point 7: 使用增強的域名查詢scope
        $website = Website::forDomain($websiteDomain)->first();

        if (!$website) {
            // Point 7: 加強網站未找到的除錯日誌
            Log::channel('wp')->warning('FormFieldMapper - 網站記錄未找到，使用預設對應', [
                'website_domain' => $websiteDomain,
                'searched_domain' => $websiteDomain,
                'fallback_method' => 'default_mapping',
                'reason' => '資料庫中無對應網站記錄',
                'suggestion' => '請檢查 websites 表中是否存在該域名記錄'
            ]);

            Log::warning("Point61 - 未找到網站記錄: {$websiteDomain}，使用預設對應");
            return $this->useDefaultMapping($rawFormData);
        }

        // Point 7: 記錄找到的網站資料
        Log::channel('wp')->info('FormFieldMapper - 網站記錄查詢成功', [
            'website_domain' => $websiteDomain,
            'website_id' => $website->id,
            'website_name' => $website->name,
            'website_status' => $website->status,
            'website_type' => $website->type,
            'found_in_database' => true
        ]);

        // 2. 取得該網站的欄位對應設定
        $fieldMappings = WebsiteFieldMapping::active()
            ->forWebsite($website->id)
            ->get()
            ->keyBy('wp_field_name');

        // Point 7: 記錄欄位對應設定查詢結果
        Log::channel('wp')->info('FormFieldMapper - 欄位對應設定查詢', [
            'website_domain' => $websiteDomain,
            'website_id' => $website->id,
            'website_name' => $website->name,
            'field_mappings_count' => $fieldMappings->count(),
            'field_mappings_empty' => $fieldMappings->isEmpty(),
            'available_mappings' => $fieldMappings->keys()->toArray()
        ]);

        if ($fieldMappings->isEmpty()) {
            Log::channel('wp')->warning('FormFieldMapper - 欄位對應設定為空，使用預設對應', [
                'website_domain' => $websiteDomain,
                'website_id' => $website->id,
                'website_name' => $website->name,
                'fallback_method' => 'default_mapping',
                'reason' => '網站存在但未設定欄位對應',
                'suggestion' => '請在網站管理中為此網站配置欄位對應'
            ]);

            Log::info("Point61 - 網站 {$websiteDomain} 未設定欄位對應，使用預設對應");
            return $this->useDefaultMapping($rawFormData);
        }

        // 3. 執行欄位映射
        $mappedData = [];
        $unmappedFields = [];

        foreach ($rawFormData as $wpFieldName => $value) {
            if ($fieldMappings->has($wpFieldName)) {
                $mapping = $fieldMappings->get($wpFieldName);
                
                // 驗證和轉換數據
                if ($mapping->validateValue($value)) {
                    $transformedValue = $mapping->transformValue($value);
                    $mappedData[$mapping->system_field] = $transformedValue;
                    
                    Log::debug("Point61 - 欄位映射: {$wpFieldName} -> {$mapping->system_field} = {$transformedValue}");
                } else {
                    Log::warning("Point61 - 欄位驗證失敗: {$wpFieldName} = {$value}");
                }
            } else {
                // 未對應的欄位保存到unmapped中
                $unmappedFields[$wpFieldName] = $value;
            }
        }

        // 4. 檢查必填欄位
        $this->validateRequiredFields($website->id, $mappedData);

        // 5. 設定預設值
        $this->setDefaultValues($website->id, $mappedData);

        // 6. 保存完整的原始資料到payload
        $mappedData['_original_payload'] = $rawFormData;
        $mappedData['_unmapped_fields'] = $unmappedFields;

        Log::info("Point61 - 欄位映射完成", [
            'website' => $websiteDomain,
            'mapped_fields' => array_keys($mappedData),
            'unmapped_fields' => array_keys($unmappedFields)
        ]);

        return $mappedData;
    }

    /**
     * 使用預設的硬編碼對應（向後兼容）
     * Point 5: 增強支援結構化表單的 title 欄位對應
     */
    protected function useDefaultMapping(array $rawFormData): array
    {
        $defaultMapping = [
            // Point 3 格式的對應（原始）
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
            '頁面_URL' => 'page_url',

            // Point 5: 結構化格式的 title 對應
            'LINE ID' => 'line_id',
            '手機' => 'phone',
            '需求金額' => 'capital_need',
            '諮詢項目' => 'loan_need',
            '所在區域' => 'region',
            '可連繫時間' => 'contact_time',
            'E-mail' => 'email',
            '遠端 IP' => 'remote_ip',
            '使用者代理' => 'user_agent',
            '表單名稱' => 'form_name',
            '表單ID' => 'form_id',

            // 其他可能的變體
            '聯絡時間' => 'contact_time',
            '手機號' => 'phone',
            '電話' => 'phone',
            '區域' => 'region',
            '地址' => 'address',
            '房屋坪數' => 'property_size',
            '貸款金額' => 'capital_need',
        ];

        $mappedData = [];
        $unmappedFields = [];

        foreach ($rawFormData as $wpFieldName => $value) {
            if (isset($defaultMapping[$wpFieldName])) {
                $systemField = $defaultMapping[$wpFieldName];
                $mappedData[$systemField] = $this->basicTransform($systemField, $value);

                Log::debug("Point5 - 欄位對應成功: {$wpFieldName} -> {$systemField} = " . ($value ?? 'null'));
            } else {
                $unmappedFields[$wpFieldName] = $value;
                Log::debug("Point5 - 未對應欄位: {$wpFieldName} = " . ($value ?? 'null'));
            }
        }

        $mappedData['_original_payload'] = $rawFormData;
        $mappedData['_unmapped_fields'] = $unmappedFields;

        Log::info("Point5 - 預設欄位對應完成", [
            'mapped_fields' => array_keys($mappedData),
            'mapped_count' => count($mappedData) - 2, // 扣除 _original_payload 和 _unmapped_fields
            'unmapped_count' => count($unmappedFields)
        ]);

        return $mappedData;
    }

    /**
     * 基本的資料轉換
     */
    protected function basicTransform(string $systemField, $value)
    {
        switch ($systemField) {
            case 'phone':
                return preg_replace('/\D+/', '', $value);
            case 'email':
                return strtolower(trim($value));
            default:
                return $value;
        }
    }

    /**
     * 驗證必填欄位
     * Point 63: 由於移除必填功能，註釋掉驗證邏輯
     */
    protected function validateRequiredFields(int $websiteId, array &$mappedData): void
    {
        // Point 63: 移除必填驗證功能
        // $requiredMappings = WebsiteFieldMapping::active()
        //     ->forWebsite($websiteId)
        //     ->where('is_required', true)
        //     ->get();

        // foreach ($requiredMappings as $mapping) {
        //     if (empty($mappedData[$mapping->system_field])) {
        //         Log::warning("Point61 - 必填欄位缺失: {$mapping->system_field} ({$mapping->wp_field_name})");
        //     }
        // }
    }

    /**
     * 設定欄位預設值
     */
    protected function setDefaultValues(int $websiteId, array &$mappedData): void
    {
        $defaultMappings = WebsiteFieldMapping::active()
            ->forWebsite($websiteId)
            ->whereNotNull('default_value')
            ->get();

        foreach ($defaultMappings as $mapping) {
            if (empty($mappedData[$mapping->system_field])) {
                $mappedData[$mapping->system_field] = $mapping->default_value;
                Log::debug("Point61 - 設定預設值: {$mapping->system_field} = {$mapping->default_value}");
            }
        }
    }

    /**
     * 從頁面URL提取網站域名
     * Point 7: 增強域名提取除錯日誌
     */
    public function extractDomainFromUrl(string $url): ?string
    {
        if (empty($url)) {
            Log::channel('wp')->debug('FormFieldMapper - URL為空，無法提取域名', [
                'input_url' => $url
            ]);
            return null;
        }

        $parsedUrl = parse_url($url);
        $extractedDomain = $parsedUrl['host'] ?? null;

        Log::channel('wp')->info('FormFieldMapper - 域名提取結果', [
            'input_url' => $url,
            'parsed_url' => $parsedUrl,
            'extracted_domain' => $extractedDomain,
            'parse_successful' => !empty($extractedDomain)
        ]);

        return $extractedDomain;
    }

    /**
     * 取得系統標準欄位清單
     */
    public function getStandardFields(): array
    {
        return WebsiteFieldMapping::getSystemFields();
    }

    /**
     * 驗證欄位對應設定
     * Point 7: 增強欄位對應驗證除錯日誌
     */
    public function validateMapping(int $websiteId): array
    {
        Log::channel('wp')->info('FormFieldMapper - 開始驗證欄位對應設定', [
            'website_id' => $websiteId
        ]);

        $errors = [];

        // 檢查是否有重複的系統欄位對應
        $systemFields = WebsiteFieldMapping::active()
            ->forWebsite($websiteId)
            ->pluck('system_field')
            ->toArray();

        Log::channel('wp')->debug('FormFieldMapper - 查詢到的系統欄位', [
            'website_id' => $websiteId,
            'system_fields' => $systemFields,
            'system_fields_count' => count($systemFields)
        ]);

        $duplicates = array_diff_assoc($systemFields, array_unique($systemFields));

        if (!empty($duplicates)) {
            $duplicateError = "系統欄位重複對應: " . implode(', ', array_unique($duplicates));
            $errors[] = $duplicateError;

            Log::channel('wp')->warning('FormFieldMapper - 發現重複的系統欄位對應', [
                'website_id' => $websiteId,
                'duplicates' => array_unique($duplicates),
                'error_message' => $duplicateError
            ]);
        }

        // 檢查必填欄位是否已設定
        $requiredFields = ['name', 'phone']; // 基本必填欄位
        $mappedSystemFields = array_unique($systemFields);

        Log::channel('wp')->debug('FormFieldMapper - 檢查必填欄位對應', [
            'website_id' => $websiteId,
            'required_fields' => $requiredFields,
            'mapped_system_fields' => $mappedSystemFields
        ]);

        foreach ($requiredFields as $field) {
            if (!in_array($field, $mappedSystemFields)) {
                $missingError = "缺少必填欄位對應: {$field}";
                $errors[] = $missingError;

                Log::channel('wp')->warning('FormFieldMapper - 缺少必填欄位對應', [
                    'website_id' => $websiteId,
                    'missing_field' => $field,
                    'error_message' => $missingError
                ]);
            }
        }

        Log::channel('wp')->info('FormFieldMapper - 欄位對應驗證完成', [
            'website_id' => $websiteId,
            'validation_errors' => $errors,
            'error_count' => count($errors),
            'validation_passed' => empty($errors)
        ]);

        return $errors;
    }

    /**
     * 為網站建立預設欄位對應
     * Point 7: 增強預設對應建立除錯日誌
     */
    public function createDefaultMappings(int $websiteId): void
    {
        Log::channel('wp')->info('FormFieldMapper - 開始為網站建立預設欄位對應', [
            'website_id' => $websiteId
        ]);

        $defaultMappings = [
            ['system_field' => 'name', 'wp_field_name' => '姓名'],
            ['system_field' => 'phone', 'wp_field_name' => '手機號碼'],
            ['system_field' => 'email', 'wp_field_name' => 'Email'],
            ['system_field' => 'line_id', 'wp_field_name' => 'LINE_ID'],
            ['system_field' => 'contact_time', 'wp_field_name' => '方便聯絡時間'],
            ['system_field' => 'capital_need', 'wp_field_name' => '資金需求'],
            ['system_field' => 'loan_need', 'wp_field_name' => '貸款需求'],
            ['system_field' => 'region', 'wp_field_name' => '房屋區域'],
            ['system_field' => 'address', 'wp_field_name' => '房屋地址'],
            ['system_field' => 'page_url', 'wp_field_name' => '頁面 URL'],
        ];

        Log::channel('wp')->debug('FormFieldMapper - 預設對應清單', [
            'website_id' => $websiteId,
            'default_mappings' => $defaultMappings,
            'mapping_count' => count($defaultMappings)
        ]);

        $createdMappings = [];
        foreach ($defaultMappings as $index => $mapping) {
            $systemFields = WebsiteFieldMapping::getSystemFields();
            $systemFieldInfo = $systemFields[$mapping['system_field']] ?? [];

            $mappingData = array_merge($mapping, [
                'website_id' => $websiteId,
                'display_name' => $systemFieldInfo['label'] ?? $mapping['wp_field_name'],
                'field_type' => 'text', // Point 63: 使用預設值
                'is_required' => false, // Point 63: 使用預設值
                'sort_order' => $index * 10,
                'is_active' => true,
            ]);

            Log::channel('wp')->debug('FormFieldMapper - 建立欄位對應', [
                'website_id' => $websiteId,
                'mapping_index' => $index,
                'mapping_data' => $mappingData
            ]);

            $createdMapping = WebsiteFieldMapping::create($mappingData);
            $createdMappings[] = $createdMapping->id;
        }

        Log::channel('wp')->info('FormFieldMapper - 預設欄位對應建立完成', [
            'website_id' => $websiteId,
            'created_mapping_ids' => $createdMappings,
            'created_count' => count($createdMappings)
        ]);

        Log::info("Point61 - 為網站 {$websiteId} 建立預設欄位對應");
    }

    /**
     * Point 62: 檢查系統欄位是否存在
     */
    public function systemFieldExists(string $fieldKey): bool
    {
        $systemFields = WebsiteFieldMapping::getSystemFields();
        return array_key_exists($fieldKey, $systemFields);
    }

    /**
     * Point 62: 新增自定義系統欄位
     * 
     * 注意：這是一個簡化實現，實際上系統欄位應該存在資料庫中
     * 目前只是將其添加到記憶體中的定義
     */
    public function addCustomSystemField(array $fieldData): bool
    {
        try {
            // 由於目前系統欄位是通過常數定義的，
            // 這裡模擬添加到一個臨時存儲中
            // 實際上應該考慮將系統欄位存入資料庫
            
            $key = $fieldData['key'];
            $label = $fieldData['label'];
            $type = $fieldData['type'] ?? 'text'; // Point 63: 使用預設值
            $required = $fieldData['required'] ?? false; // Point 63: 使用預設值
            $description = $fieldData['description'] ?? '';

            // 暫時通過快取或設定檔案存儲自定義欄位
            // 這是一個簡化的實現，生產環境建議使用資料庫
            $customFields = cache()->get('custom_system_fields', []);
            $customFields[$key] = [
                'label' => $label,
                'type' => $type,
                'required' => $required,
                'description' => $description,
                'custom' => true
            ];
            
            cache()->put('custom_system_fields', $customFields, 86400); // 快取24小時

            Log::info("Point62 - 新增自定義系統欄位成功", $fieldData);
            return true;

        } catch (\Exception $e) {
            Log::error("Point62 - 新增自定義系統欄位失敗", [
                'field_data' => $fieldData,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}