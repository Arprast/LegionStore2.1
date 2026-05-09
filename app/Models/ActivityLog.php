<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_logs';
    protected $fillable = ['user_id', 'action', 'description'];

    // Relasi agar kita tahu siapa nama karyawan berdasarkan user_id
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}