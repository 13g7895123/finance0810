<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type','entity_id','field_id','value','updated_by'
    ];

    public function field()
    {
        return $this->belongsTo(CustomField::class, 'field_id');
    }
}
