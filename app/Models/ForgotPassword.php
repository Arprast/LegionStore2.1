<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
class ForgotPassword extends Model
{
    use HasFactory;
    protected $table='forgot_passwords';
    protected $primaryKey='id';
    public $incrementing=true;
    public $timestamps=false;
    protected $keyType='integer';
    protected $fillable=[
      'id',
      'user_id',
      'otp_code',
      'req_id',
      'created_at',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id' 
        );
    }
}
