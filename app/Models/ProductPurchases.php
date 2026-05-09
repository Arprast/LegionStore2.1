<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
class ProductPurchases extends Model
{
    use HasFactory;
    protected $table='product_purchases';
    protected $primaryKey='id';
    public $incrementing=true;
    public $timestamps=false;
    protected $keyType='integer';
    protected $fillable=[
      'id',
      'product_id',
      'user_id',
      'account_id',
      'qty',
      'status',
      'email',
      'no_wa',
      'created_at',
      'completed_at',
      'name',
      'price',
      'source' ,
      'order_id',
      'midtrans_order_id',
      'customer_no',
      'payment_method'
    ];

    public function products()
    {
        return $this->belongsTo(
            Product::class,
            'product_id',
            'id' 
        );
    }
    public function users()
    {
        return $this->belongsTo(
            User::class,
            'user_id',
            'id' 
        );
    }
    public function ratings()
    {
        return $this->hasMany(
            Rating::class,
            'product_purchase_id', 
            'id'  
        );
    }
}
