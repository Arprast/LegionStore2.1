<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topup extends Model
{
    use HasFactory;

    /**
     * Kolom yang bisa diisi secara massal.
     * Penting agar tidak terjadi error MassAssignment di Webhook.
     */
    protected $fillable = [
        'order_id', 
        'user_id', 
        'amount', 
        'status'
    ];

    /**
     * Relasi ke User pemilik transaksi.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}