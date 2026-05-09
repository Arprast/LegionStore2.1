<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use App\Models\Game;
use App\Models\Product;
class ProductCategory extends Model
{
    use HasFactory;
    protected $table='product_category';
    protected $primaryKey='id';
    public $incrementing=true;
    public $timestamps=false;
    protected $keyType='integer';
    protected $fillable=[
      'id',
      'game_id',
      'name',
      'image'
    ];

    public function game()
    {
        return $this->belongsTo(
            Game::class,
            'game_id',
            'id' 
        );
    }
    public function products()
    {
        return $this->hasMany(
            Product::class,
            'product_category_id', 
            'id'  
        );
    }
}
