<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Game;
use App\Models\GameVisit;
use App\Models\ProductPurchases;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class TrendingController extends Controller
{
    // 1. Tampilkan Halaman View
    public function index()
    {
        return view('admin.trending.index');
    }

    // 2. Supply Data untuk DataTables
    public function data(Request $request)
    {
        // Tangkap filter dari request (hari, minggu, bulan, semua)
        $filter = $request->input('filter_waktu', 'semua');
        $now = Carbon::now();

        // Kita gunakan Subquery agar DataTables bisa melakukan Sorting (Order By) dengan sempurna
        $games = Game::select('games.*')
            
            // Subquery 1: Total Pengunjung User (Login)
            ->selectSub(function($q) use ($filter, $now) {
                $q->from('game_visits')
                  ->whereColumn('game_visits.game_id', 'games.id')
                  ->whereNotNull('game_visits.user_id')
                  ->selectRaw('COUNT(*)');
                $this->applyTimeFilter($q, $filter, $now, 'game_visits.created_at');
            }, 'visit_user')

            // Subquery 2: Total Pengunjung Guest (Belum Login)
            ->selectSub(function($q) use ($filter, $now) {
                $q->from('game_visits')
                  ->whereColumn('game_visits.game_id', 'games.id')
                  ->whereNull('game_visits.user_id')
                  ->selectRaw('COUNT(*)');
                $this->applyTimeFilter($q, $filter, $now, 'game_visits.created_at');
            }, 'visit_guest')

            // Subquery 3: Total Jumlah Transaksi Sukses
            ->selectSub(function($q) use ($filter, $now) {
                $q->from('product_purchases')
                  ->join('products', 'product_purchases.product_id', '=', 'products.id')
                  ->join('product_category', 'products.product_category_id', '=', 'product_category.id')
                  ->whereColumn('product_category.game_id', 'games.id')
                  ->where('product_purchases.status', 'success')
                  ->selectRaw('COUNT(*)');
                $this->applyTimeFilter($q, $filter, $now, 'product_purchases.created_at');
            }, 'total_transaksi')

            // Subquery 4: Total Nominal (Rupiah) Transaksi Sukses
            ->selectSub(function($q) use ($filter, $now) {
                $q->from('product_purchases')
                  ->join('products', 'product_purchases.product_id', '=', 'products.id')
                  ->join('product_category', 'products.product_category_id', '=', 'product_category.id')
                  ->whereColumn('product_category.game_id', 'games.id')
                  ->where('product_purchases.status', 'success')
                  ->selectRaw('COALESCE(SUM(product_purchases.price), 0)');
                $this->applyTimeFilter($q, $filter, $now, 'product_purchases.created_at');
            }, 'total_nominal');


        return DataTables::of($games)
            ->addIndexColumn()
            
            // Format angka agar rapi
            ->editColumn('total_transaksi', function ($row) {
                return number_format($row->total_transaksi, 0, ',', '.');
            })
            ->editColumn('total_nominal', function ($row) {
                return "Rp " . number_format($row->total_nominal, 0, ',', '.');
            })

            // Kolom Status Trending
            ->addColumn('status_trending', function ($row) {
                if ($row->is_trending == '1') {
                    return '<span class="badge" style="background-color: #28a745; padding: 5px 10px; border-radius: 8px;">Trending</span>';
                }
                return '<span class="badge" style="background-color: #6c757d; padding: 5px 10px; border-radius: 8px;">Biasa</span>';
            })

            // Tombol Aksi (Jadikan Trending / Non-Trending)
            ->addColumn('action', function ($row) {
                if ($row->is_trending == '1') {
                    return '<button data-id="' . $row->id . '" class="btn btn-sm btn-dark toggle-trending-btn"><i class="fa fa-star-o"></i> Non-Trending</button>';
                } else {
                    return '<button data-id="' . $row->id . '" class="btn btn-sm btn-warning toggle-trending-btn"><i class="fa fa-star"></i> Jadikan Trending</button>';
                }
            })
            ->rawColumns(['status_trending', 'action'])
            ->make(true);
    }

    // 3. Fungsi Logika Tombol Trending (Dipindahkan dari GameController)
    public function toggleTrending(Request $req, $id){      
        $game = Game::find($id);
        if (!$game) {
            return response()->json([
                'status' => 'error',
                'message' => 'Game tidak ditemukan'
            ], 404);
        }

        // Balikkan statusnya: Jika 1 jadi 0, jika 0 jadi 1
        $game->is_trending = $game->is_trending == '1' ? '0' : '1';
        $simpan = $game->save();

        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Status trending berhasil diubah!'
            ], 200);
        }
        return response()->json([
            'status' => 'error',
            'message' => 'Gagal mengubah status, silakan coba lagi'
        ], 500);
    }

    // --- FUNGSI BANTUAN UNTUK FILTER WAKTU ---
    private function applyTimeFilter($query, $filter, $now, $columnName)
    {
        if ($filter == 'hari') {
            $query->whereDate($columnName, $now->toDateString());
        } elseif ($filter == 'minggu') {
            $query->whereBetween($columnName, [
                $now->copy()->startOfWeek()->toDateTimeString(), 
                $now->copy()->endOfWeek()->toDateTimeString()
            ]);
        } elseif ($filter == 'bulan') {
            $query->whereMonth($columnName, $now->month)
                  ->whereYear($columnName, $now->year);
        }
    }
}