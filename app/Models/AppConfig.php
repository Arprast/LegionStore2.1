<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppConfig extends Model
{
    use HasFactory;

    protected $table = 'app_configs';

    protected $fillable = [
        'flash_sale_start',
        'flash_sale_end',
        'is_flash_sale_active', 
        'website_status',
        'auto_active_at',
        'maintenance_message',
        'biaya_admin',
    ];
}