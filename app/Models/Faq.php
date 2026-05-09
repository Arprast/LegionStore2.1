<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use App\Models\Game;
class Faq extends Model
{
    use HasFactory;
    protected $table='faq';
    protected $primaryKey='id';
    public $incrementing=true;
    public $timestamps=false;
    protected $keyType='integer';
    protected $fillable=[
      'id',
      'game_id',
      'title',
      'content',
    ];

    public function game()
    {
        return $this->belongsTo(
            Game::class,
            'game_id',
            'id' 
        );
    }
}
