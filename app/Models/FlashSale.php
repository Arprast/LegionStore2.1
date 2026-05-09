<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use App\Models\Product;
class FlashSale extends Model
{
    use HasFactory;
    protected $table='flash_sales';
    protected $primaryKey='id';
    public $incrementing=true;
    public $timestamps=false;
    protected $keyType='integer';
    protected $fillable=[
      'id',
      'product_id',
      'normal_price', // Tambahan
      'price',        // Harga flash sale
      'qty',          // Opsional jika Anda pakai ini
      'stock',        // Limit flash sale
      'is_locked',    // Tambahan
    ];

    public function product()
    {
        return $this->belongsTo(
            Product::class,
            'product_id',
            'id' 
        );
    }
}
