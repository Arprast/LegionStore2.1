<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use App\Models\ProductCategory;
use App\Models\ProductPurchases;
use App\Models\FlashSale;
use App\Models\Rating;
class Product extends Model
{
    use HasFactory;
    protected $table='products';
    protected $primaryKey='id';
    public $incrementing=true;
    public $timestamps=false;
    protected $keyType='integer';
    protected $fillable=[
      'id',
      'product_category_id',
      'name',
      'cap_price',
      'selling_price',
      'discount',
      'active',
      'sku_code',
      "buyer_product_status",
      "seller_product_status",
    ];

    public function product_category()
    {
        return $this->belongsTo(
            ProductCategory::class,
            'product_category_id',
            'id' 
        );
    }
    public function flash_sales()
    {
        return $this->hasOne(
            FlashSale::class,
            'product_id',
            'id' 
        );
    }
    public function product_purchases()
    {
        return $this->hasMany(
            ProductPurchases::class,
            'product_id', 
            'id'  
        );
    }

    public function ratings()
    {
        return $this->hasManyThrough(
            Rating::class,
            ProductPurchase::class,
            'product_id',          // FK di product_purchases
            'product_purchase_id', // FK di ratings
            'id',
            'id'
        );
    }
}
