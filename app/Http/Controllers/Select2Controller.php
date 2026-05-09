<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Game;
use App\Models\Product;
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;
class Select2Controller extends Controller
{
    public function game(Request $req){
        $query = Game::query();

        if ($req->filled('q')) {
            $query->where('name', 'like', '%'.$req->q.'%');
        }
        $perPage = 10;
        $page = $req->page ?? 1;
        $game = $query
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $game->items(),
            'more' => $game->hasMorePages(),
        ]);
    }
    public function product(Request $req){
        $query = Product::query();
        if($req->filled('game_id')){
            $query->where('game_id',$req->game_id);
        }
        if ($req->filled('q')) {
            $query->where('name', 'like', '%'.$req->q.'%');
        }
        $perPage = 10;
        $page = $req->page ?? 1;
        $product = $query
            ->orderBy('name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $product->items(),
            'more' => $product->hasMorePages(),
        ]);
    }
}
