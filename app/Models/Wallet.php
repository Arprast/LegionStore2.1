<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    /**
     * Kolom yang bisa diisi secara massal.
     * Mengatasi error [user_id] to fillable property yang tadi muncul di log.
     */
    protected $fillable = [
        'user_id', 
        'balance'
    ];

    /**
     * Relasi ke User pemilik dompet.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // RELASI BARU: Ke Riwayat Mutasi
    public function mutations()
    {
        return $this->hasMany(WalletMutation::class, 'user_id', 'user_id');
    }
}