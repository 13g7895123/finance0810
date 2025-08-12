<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerIdentifier extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'type', // phone | email | line
        'value',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
