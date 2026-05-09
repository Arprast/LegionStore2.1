<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use App\Models\ProductPurchases;
class Rating extends Model
{
    use HasFactory;
    protected $table='ratings';
    protected $primaryKey='id';
    public $incrementing=true;
    public $timestamps=false;
    protected $keyType='integer';
    protected $fillable=[
      'id',
      'rating',
      'reviews', 
      'product_purchase_id',
      'created_at',
    ];

    public function product_purchases()
    {
        return $this->belongsTo(
            ProductPurchases::class,
            'product_purchase_id',
            'id' 
        );
    }
}
