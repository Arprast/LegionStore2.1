<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    use HasFactory;

    protected $table = 'promos';
    
    protected $fillable = [
        'code', 
        'type', 
        'value', 
        'max_discount', 
        'quota', 
        'used', 
        'valid_until', 
        'is_active'
    ];
}