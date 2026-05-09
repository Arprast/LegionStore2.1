<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rating;
use App\Models\Game;
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;
class RatingController extends Controller
{
    public function index(){
        $data = Game::select('games.*')
        ->selectSub(function($q) {
            $q->from('ratings')
            ->join('product_purchases', 'ratings.product_purchase_id', '=', 'product_purchases.id')
            ->join('products', 'product_purchases.product_id', '=', 'products.id')
            ->whereColumn('products.game_id', 'games.id')
            ->where('product_purchases.status', 'success')
            ->selectRaw('AVG(ratings.rating)');
        }, 'avg_rating')
        ->get();
        return response()->json($data);
        return view('admin.rating.index');
    }
    public function data()
    {
        return DataTables::of(ProductPurchases::with('users'))
            ->addIndexColumn()
            ->addColumn('username', function ($row) {
                  return $row->users->name;
              })
            ->editColumn('created_at', function ($row) {
                  return Carbon::parse($row->created_at)
                      ->timezone('Asia/Jakarta')
                      ->format('d-m-Y H:i');
              })
              ->editColumn('completed_at', function ($row) {
                  return Carbon::parse($row->completed_at)
                      ->timezone('Asia/Jakarta')
                      ->format('d-m-Y H:i');
              })
            ->addColumn('action', function ($row) {
              $option='
                  <div class="btn-group">
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-success process-btn">Proses Segera</a>
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-warning cancel-btn">Batalkan</a>
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn">Hapus</a>
                  </div>
                ';
                if($row->status=="success"){
                  $option='<div class="btn-group">
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-warning print-btn">Cetak Struk</a>
                    
                  </div>';
                }
                else if(($row->status=="failed")||($row->status=="cancel")){
                  $option='<div class="btn-group">
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn">Hapus</a>                    
                  </div>';
                }
                return $option;
            })
            ->rawColumns(['action'])
            ->make(true);
    }
}
