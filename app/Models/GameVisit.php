<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameVisit extends Model
{
    use HasFactory;

    protected $table = 'game_visits';
    protected $fillable = ['game_id', 'user_id', 'ip_address'];

    // Relasi balik ke Game
    public function game()
    {
        return $this->belongsTo(Game::class, 'game_id', 'id');
    }
}   