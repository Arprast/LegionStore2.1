<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
class SpecialOffers extends Model
{
    use HasFactory;
    protected $table='special_offers';
    protected $primaryKey='id';
    public $incrementing=true;
    public $timestamps=false;
    protected $keyType='integer';
    protected $fillable=[
      'id',
      'image',
      'title',
      'link',
      'content',
      'is_active',
    ];
}
