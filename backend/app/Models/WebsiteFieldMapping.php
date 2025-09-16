<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Point 61: WordPress網站表單欄位對應模型
 */
class WebsiteFieldMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'system_field',
        'wp_field_name',
        'display_name',
        'is_required',
        'field_type',
        'validation_rules',
        'transform_rules',
        'default_value',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'validation_rules' => 'array',
        'transform_rules' => 'array',
        'sort_order' => 'integer',
    ];

    // 欄位類型常數
    const FIELD_TYPE_TEXT = 'text';
    const FIELD_TYPE_EMAIL = 'email';
    const FIELD_TYPE_PHONE = 'phone';
    const FIELD_TYPE_NUMBER = 'number';
    const FIELD_TYPE_DATE = 'date';
    const FIELD_TYPE_TIME = 'time';
    const FIELD_TYPE_DATETIME = 'datetime';
    const FIELD_TYPE_URL = 'url';
    const FIELD_TYPE_SELECT = 'select';
    const FIELD_TYPE_TEXTAREA = 'textarea';

    // 系統標準欄位常數
    const SYSTEM_FIELD_NAME = 'name';
    const SYSTEM_FIELD_PHONE = 'phone';
    const SYSTEM_FIELD_EMAIL = 'email';
    const SYSTEM_FIELD_LINE_ID = 'line_id';
    const SYSTEM_FIELD_CONTACT_TIME = 'contact_time';
    const SYSTEM_FIELD_CAPITAL_NEED = 'capital_need';
    const SYSTEM_FIELD_LOAN_NEED = 'loan_need';
    const SYSTEM_FIELD_REGION = 'region';
    const SYSTEM_FIELD_ADDRESS = 'address';
    const SYSTEM_FIELD_DATE = 'date';
    const SYSTEM_FIELD_TIME = 'time';
    const SYSTEM_FIELD_PAGE_URL = 'page_url';

    /**
     * 取得關聯的網站
     */
    public function website()
    {
        return $this->belongsTo(Website::class);
    }

    /**
     * 範圍查詢：只取得啟用的對應
     * Point 7: 增強網站管理資料查詢日誌
     */
    public function scopeActive($query)
    {
        Log::channel('wp')->debug('WebsiteFieldMapping - Active scope查詢', [
            'scope' => 'active',
            'condition' => 'is_active = true',
            'model' => 'WebsiteFieldMapping'
        ]);

        return $query->where('is_active', true);
    }

    /**
     * 範圍查詢：依網站ID查詢
     * Point 7: 增強網站管理資料查詢日誌
     */
    public function scopeForWebsite($query, $websiteId)
    {
        Log::channel('wp')->info('WebsiteFieldMapping - ForWebsite scope查詢', [
            'scope' => 'forWebsite',
            'website_id' => $websiteId,
            'condition' => "website_id = {$websiteId}",
            'model' => 'WebsiteFieldMapping',
            'is_mrmoney_debug' => true
        ]);

        return $query->where('website_id', $websiteId);
    }

    /**
     * 範圍查詢：依系統欄位查詢
     * Point 7: 增強網站管理資料查詢日誌
     */
    public function scopeForSystemField($query, $systemField)
    {
        Log::channel('wp')->debug('WebsiteFieldMapping - ForSystemField scope查詢', [
            'scope' => 'forSystemField',
            'system_field' => $systemField,
            'condition' => "system_field = '{$systemField}'",
            'model' => 'WebsiteFieldMapping'
        ]);

        return $query->where('system_field', $systemField);
    }

    /**
     * 範圍查詢：依排序順序排列
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 取得系統標準欄位清單
     * Point 62: 包含自定義系統欄位
     */
    public static function getSystemFields()
    {
        $standardFields = [
            self::SYSTEM_FIELD_NAME => [
                'label' => '姓名',
                'type' => self::FIELD_TYPE_TEXT,
                'required' => true,
                'description' => '客戶姓名'
            ],
            self::SYSTEM_FIELD_PHONE => [
                'label' => '手機號碼',
                'type' => self::FIELD_TYPE_PHONE,
                'required' => true,
                'description' => '客戶聯絡手機'
            ],
            self::SYSTEM_FIELD_EMAIL => [
                'label' => '電子郵件',
                'type' => self::FIELD_TYPE_EMAIL,
                'required' => false,
                'description' => '客戶電子郵件地址'
            ],
            self::SYSTEM_FIELD_LINE_ID => [
                'label' => 'LINE ID',
                'type' => self::FIELD_TYPE_TEXT,
                'required' => false,
                'description' => '客戶LINE識別碼'
            ],
            self::SYSTEM_FIELD_CONTACT_TIME => [
                'label' => '方便聯絡時間',
                'type' => self::FIELD_TYPE_TEXT,
                'required' => false,
                'description' => '客戶偏好的聯絡時間'
            ],
            self::SYSTEM_FIELD_CAPITAL_NEED => [
                'label' => '資金需求',
                'type' => self::FIELD_TYPE_TEXT,
                'required' => false,
                'description' => '客戶資金需求額度'
            ],
            self::SYSTEM_FIELD_LOAN_NEED => [
                'label' => '貸款需求',
                'type' => self::FIELD_TYPE_TEXT,
                'required' => false,
                'description' => '客戶貸款類型需求'
            ],
            self::SYSTEM_FIELD_REGION => [
                'label' => '房屋區域',
                'type' => self::FIELD_TYPE_TEXT,
                'required' => false,
                'description' => '房屋所在區域'
            ],
            self::SYSTEM_FIELD_ADDRESS => [
                'label' => '房屋地址',
                'type' => self::FIELD_TYPE_TEXT,
                'required' => false,
                'description' => '房屋詳細地址'
            ],
            self::SYSTEM_FIELD_DATE => [
                'label' => '日期',
                'type' => self::FIELD_TYPE_DATE,
                'required' => false,
                'description' => '表單提交日期'
            ],
            self::SYSTEM_FIELD_TIME => [
                'label' => '時間',
                'type' => self::FIELD_TYPE_TIME,
                'required' => false,
                'description' => '表單提交時間'
            ],
            self::SYSTEM_FIELD_PAGE_URL => [
                'label' => '頁面網址',
                'type' => self::FIELD_TYPE_URL,
                'required' => false,
                'description' => '表單提交的頁面網址'
            ],
        ];

        // Point 62: 合併自定義系統欄位
        $customFields = cache()->get('custom_system_fields', []);
        return array_merge($standardFields, $customFields);
    }

    /**
     * 取得欄位類型清單
     */
    public static function getFieldTypes()
    {
        return [
            self::FIELD_TYPE_TEXT => '文字',
            self::FIELD_TYPE_EMAIL => '電子郵件',
            self::FIELD_TYPE_PHONE => '電話號碼',
            self::FIELD_TYPE_NUMBER => '數字',
            self::FIELD_TYPE_DATE => '日期',
            self::FIELD_TYPE_TIME => '時間',
            self::FIELD_TYPE_DATETIME => '日期時間',
            self::FIELD_TYPE_URL => '網址',
            self::FIELD_TYPE_SELECT => '下拉選單',
            self::FIELD_TYPE_TEXTAREA => '多行文字',
        ];
    }

    /**
     * 驗證欄位值
     */
    public function validateValue($value)
    {
        if (!$this->validation_rules) {
            return true;
        }

        // 這裡可以實作各種驗證邏輯
        // 例如：required, email, phone format等
        
        return true;
    }

    /**
     * 轉換欄位值
     */
    public function transformValue($value)
    {
        if (!$this->transform_rules || !$value) {
            return $value;
        }

        // 依據欄位類型和轉換規則處理數據
        switch ($this->field_type) {
            case self::FIELD_TYPE_PHONE:
                // 標準化電話號碼格式
                return preg_replace('/\D+/', '', $value);
                
            case self::FIELD_TYPE_EMAIL:
                // 轉換為小寫
                return strtolower(trim($value));
                
            default:
                return $value;
        }
    }

    /**
     * 取得欄位的完整配置
     */
    public function getFieldConfig()
    {
        return [
            'system_field' => $this->system_field,
            'wp_field_name' => $this->wp_field_name,
            'display_name' => $this->display_name,
            'field_type' => $this->field_type,
            'is_required' => $this->is_required,
            'validation_rules' => $this->validation_rules,
            'transform_rules' => $this->transform_rules,
            'default_value' => $this->default_value,
            'description' => $this->description,
        ];
    }
}