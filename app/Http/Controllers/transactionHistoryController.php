<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Game;
use App\Models\ProductPurchases;
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;
class transactionHistoryController extends Controller
{
    public function index(){
        // $data=Game::all();
        return view('transaction_history');
    }
    public function data(Request $request)
    {
        $query=ProductPurchases::with('users','products')->where('user_id',auth()->user()->id);
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // filter tanggal mulai
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        // filter tanggal akhir
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
              $html='
                  <div class="btn-group">
                    <button href="#" data-id="'.$row->id.'" class="btn btn-xs btn-warning detail-btn">Detail</button>
                  </div>
                ';
                return $html;
            })
            ->rawColumns(['action'])
            ->make(true);
    }
    public function getOne($id){
      $data=ProductPurchases::with('users','products')->where('user_id',auth()->user()->id)->find($id);
      return response()->json([
                'status' => 'success',
                'data'=>$data]
            , 200); 
    }
    public function dataCoin(Request $request)
    {
        $query = \App\Models\Topup::where('user_id', auth()->user()->id);

        if ($request->status) {
            // Karena status koin hanya ada pending, success, failed
            $query->where('status', $request->status);
        }

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('amount', function($row){
                return 'Rp ' . number_format($row->amount, 0, ',', '.');
            })
            ->editColumn('status', function($row){
                if($row->status == 'success'){
                    return '<span class="badge bg-success text-white px-3 py-2 rounded-pill">Success</span>';
                } elseif($row->status == 'pending'){
                    return '<span class="badge bg-warning text-dark px-3 py-2 rounded-pill">Pending</span>';
                } else {
                    return '<span class="badge bg-danger text-white px-3 py-2 rounded-pill">Failed</span>';
                }
            })
            ->editColumn('created_at', function($row){
                return $row->created_at->format('d M Y H:i');
            })
            ->rawColumns(['status'])
            ->make(true);
    }
}
