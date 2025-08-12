<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomField extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type','key','label','type','options','is_required','is_filterable','is_visible','group','sort_order','validation_rules','default_value'
    ];

    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'is_required' => 'boolean',
        'is_filterable' => 'boolean',
        'is_visible' => 'boolean',
    ];

    public function values()
    {
        return $this->hasMany(CustomFieldValue::class, 'field_id');
    }
}
