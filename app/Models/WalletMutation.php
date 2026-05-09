<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reference_id',
        'type',
        'amount',
        'description',
    ];

    /**
     * Relasi ke User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}