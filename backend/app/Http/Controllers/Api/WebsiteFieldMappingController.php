<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteFieldMapping;
use App\Services\FormFieldMapper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Point 61: WordPress網站欄位對應管理控制器
 */
class WebsiteFieldMappingController extends Controller
{
    protected FormFieldMapper $fieldMapper;

    public function __construct(FormFieldMapper $fieldMapper)
    {
        $this->fieldMapper = $fieldMapper;
    }

    /**
     * 取得指定網站的欄位對應列表
     */
    public function index(Website $website)
    {
        $mappings = $website->activeFieldMappings()
            ->with(['website'])
            ->get();

        return response()->json([
            'data' => $mappings,
            'system_fields' => WebsiteFieldMapping::getSystemFields(),
            'field_types' => WebsiteFieldMapping::getFieldTypes(),
        ]);
    }

    /**
     * 儲存網站的欄位對應設定
     */
    public function store(Request $request, Website $website)
    {
        $validator = Validator::make($request->all(), [
            'mappings' => 'required|array|min:1',
            'mappings.*.system_field' => 'required|string|max:50',
            'mappings.*.wp_field_name' => 'required|string|max:100',
            'mappings.*.display_name' => 'nullable|string|max:100', // Point 62: 改為可選，會自動使用wp_field_name
            // Point 63: 移除 field_type 和 is_required 驗證
            'mappings.*.validation_rules' => 'nullable|array',
            'mappings.*.transform_rules' => 'nullable|array',
            'mappings.*.default_value' => 'nullable|string|max:255',
            'mappings.*.description' => 'nullable|string|max:500',
            'mappings.*.sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => '欄位對應資料驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 驗證對應設定的完整性
            $mappingData = $request->input('mappings');
            $validationErrors = $this->validateMappingData($mappingData);
            
            if (!empty($validationErrors)) {
                return response()->json([
                    'error' => '欄位對應設定驗證失敗',
                    'validation_errors' => $validationErrors
                ], 422);
            }

            // 刪除該網站的現有對應
            $website->fieldMappings()->delete();

            // 建立新的對應設定
            $createdMappings = [];
            foreach ($mappingData as $index => $mapping) {
                $fieldMapping = WebsiteFieldMapping::create([
                    'website_id' => $website->id,
                    'system_field' => $mapping['system_field'],
                    'wp_field_name' => $mapping['wp_field_name'],
                    'display_name' => $mapping['display_name'] ?? $mapping['wp_field_name'], // Point 62: 自動使用wp_field_name
                    'field_type' => 'text', // Point 63: 使用預設值
                    'is_required' => false, // Point 63: 使用預設值
                    'validation_rules' => $mapping['validation_rules'] ?? null,
                    'transform_rules' => $mapping['transform_rules'] ?? null,
                    'default_value' => $mapping['default_value'] ?? null,
                    'description' => $mapping['description'] ?? null,
                    'sort_order' => $mapping['sort_order'] ?? $index * 10,
                    'is_active' => true,
                ]);

                $createdMappings[] = $fieldMapping;
            }

            Log::info('Point61 - 網站欄位對應更新成功', [
                'website_id' => $website->id,
                'website_domain' => $website->domain,
                'mappings_count' => count($createdMappings)
            ]);

            return response()->json([
                'message' => '欄位對應設定已更新',
                'data' => $createdMappings
            ]);

        } catch (\Exception $e) {
            Log::error('Point61 - 欄位對應設定儲存失敗', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => '欄位對應設定儲存失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 建立預設欄位對應
     */
    public function createDefaults(Website $website)
    {
        try {
            // 檢查是否已有對應設定
            if ($website->hasFieldMappings()) {
                return response()->json([
                    'error' => '該網站已有欄位對應設定'
                ], 409);
            }

            // 建立預設對應
            $this->fieldMapper->createDefaultMappings($website->id);

            $mappings = $website->activeFieldMappings()->get();

            return response()->json([
                'message' => '預設欄位對應已建立',
                'data' => $mappings
            ]);

        } catch (\Exception $e) {
            Log::error('Point61 - 建立預設欄位對應失敗', [
                'website_id' => $website->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => '建立預設欄位對應失敗：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 測試欄位對應設定
     */
    public function test(Request $request, Website $website)
    {
        $validator = Validator::make($request->all(), [
            'test_data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => '測試資料驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $testData = $request->input('test_data');
            
            // 使用FormFieldMapper測試對應
            $mappedResult = $this->fieldMapper->mapFields($website->domain, $testData);

            return response()->json([
                'message' => '欄位對應測試完成',
                'original_data' => $testData,
                'mapped_result' => $mappedResult,
                'mapped_fields_count' => count(array_filter($mappedResult, fn($key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY)),
                'unmapped_fields_count' => count($mappedResult['_unmapped_fields'] ?? [])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => '欄位對應測試失敗：' . $e->getMessage(),
                'original_data' => $testData
            ], 500);
        }
    }

    /**
     * 取得系統標準欄位清單
     */
    public function systemFields()
    {
        return response()->json([
            'system_fields' => WebsiteFieldMapping::getSystemFields(),
            'field_types' => WebsiteFieldMapping::getFieldTypes(),
        ]);
    }

    /**
     * 驗證欄位對應資料
     */
    protected function validateMappingData(array $mappingData): array
    {
        $errors = [];
        $systemFields = [];
        $wpFields = [];

        foreach ($mappingData as $index => $mapping) {
            $systemField = $mapping['system_field'];
            $wpField = $mapping['wp_field_name'];

            // 檢查系統欄位是否重複
            if (in_array($systemField, $systemFields)) {
                $errors[] = "系統欄位「{$systemField}」不可重複對應（項目 " . ($index + 1) . "）";
            }
            $systemFields[] = $systemField;

            // 檢查WordPress欄位名稱是否重複
            if (in_array($wpField, $wpFields)) {
                $errors[] = "WordPress欄位「{$wpField}」不可重複對應（項目 " . ($index + 1) . "）";
            }
            $wpFields[] = $wpField;

            // 檢查系統欄位是否有效
            $validSystemFields = array_keys(WebsiteFieldMapping::getSystemFields());
            if (!in_array($systemField, $validSystemFields)) {
                $errors[] = "無效的系統欄位「{$systemField}」（項目 " . ($index + 1) . "）";
            }
        }

        // 檢查必填欄位是否已對應
        $requiredFields = ['name', 'phone']; // 基本必填欄位
        foreach ($requiredFields as $requiredField) {
            if (!in_array($requiredField, $systemFields)) {
                $systemFieldInfo = WebsiteFieldMapping::getSystemFields()[$requiredField] ?? [];
                $fieldLabel = $systemFieldInfo['label'] ?? $requiredField;
                $errors[] = "必填欄位「{$fieldLabel}」未設定對應";
            }
        }

        return $errors;
    }

    /**
     * Point 62: 新增自定義系統欄位
     */
    public function addSystemField(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'label' => 'required|string|max:100',
            // Point 63: 移除 type 和 required 驗證
            'description' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => '欄位驗證失敗',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 檢查欄位代碼是否已存在
            if ($this->fieldMapper->systemFieldExists($request->key)) {
                return response()->json([
                    'error' => '欄位代碼已存在',
                    'message' => "系統欄位 '{$request->key}' 已存在"
                ], 409);
            }

            // 添加新的系統欄位
            $fieldData = [
                'key' => $request->key,
                'label' => $request->label,
                'description' => $request->description ?? '',
                // Point 63: 移除 type 和 required，使用預設值
                'type' => 'text',
                'required' => false
            ];

            $success = $this->fieldMapper->addCustomSystemField($fieldData);

            if (!$success) {
                return response()->json([
                    'error' => '新增失敗',
                    'message' => '系統欄位新增失敗'
                ], 500);
            }

            Log::info("Point62 - 新增自定義系統欄位: {$request->key}", $fieldData);

            return response()->json([
                'message' => '系統欄位新增成功',
                'field' => $fieldData
            ]);

        } catch (\Exception $e) {
            Log::error("Point62 - 新增系統欄位失敗", [
                'key' => $request->key,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => '系統錯誤',
                'message' => '新增系統欄位時發生錯誤'
            ], 500);
        }
    }
}