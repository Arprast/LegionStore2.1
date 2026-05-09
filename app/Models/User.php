<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\ProductPurchases;
use App\Models\ForgotPassword;
use App\Models\Wallet; // Import Model Wallet
use App\Models\Topup;  // Import Model Topup

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = true;
    protected $keyType = 'integer';

    protected $fillable = [
        'name',
        'email',
        'role',
        'password',
        'otp',
        'is_verified',
        'phone_number' // <--- Tambahkan baris ini
    ];

    protected $hidden = [
        'password',
        'otp'
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * RELASI BARU: Ke Wallet (Dompet Koin)
     * Ini yang bikin saldo bisa muncul di profil
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id', 'id');
    }

    /**
     * RELASI BARU: Ke Riwayat Topup
     */
    public function topups()
    {
        return $this->hasMany(Topup::class, 'user_id', 'id');
    }

    public function product_purchases()
    {
        return $this->hasMany(
            ProductPurchases::class,
            'user_id',
            'id' 
        );
    }

    public function forgot_passwords()
    {
        return $this->hasMany(
            ForgotPassword::class,
            'user_id',
            'id' 
        );
    }
}