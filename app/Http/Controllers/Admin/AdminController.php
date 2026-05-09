<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Game;
use App\Models\Product;
use App\Models\ProductPurchases;
use App\Models\Topup;
use App\Models\GameVisit; // Tambahan untuk membaca log kunjungan
use Carbon\Carbon; // Wajib ditambahkan untuk memanipulasi waktu

class AdminController extends Controller
{
   public function index(Request $request)
    {
        // Data Metrik Utama
        $users = User::count();
        $games = Game::count();
        $product = Product::count();

        // Data Transaksi Real-Time
        $pembelianMingguIni = ProductPurchases::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count();
        
        $pembelianBulanIni = ProductPurchases::whereMonth('created_at', Carbon::now()->month)
                                             ->whereYear('created_at', Carbon::now()->year)
                                             ->count();

        // Data untuk Grafik (7 Hari Terakhir)
        $chartLabels = [];
        $chartData = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $chartLabels[] = $date->format('D'); 
            
            $totalPerHari = ProductPurchases::whereDate('created_at', $date->format('Y-m-d'))->count();
            $chartData[] = $totalPerHari;
        }

        $now = Carbon::now();

        // 1. Rekap Kunjungan Bulan Ini (User & Guest)
        $visitUserBulanIni = GameVisit::whereNotNull('user_id')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $visitGuestBulanIni = GameVisit::whereNull('user_id')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        // 2. Top 5 Game Berdasarkan Transaksi Sukses Bulan Ini
        $topGames = Game::select('games.*')
            ->selectSub(function($q) use ($now) {
                $q->from('product_purchases')
                  ->join('products', 'product_purchases.product_id', '=', 'products.id')
                  ->join('product_category', 'products.product_category_id', '=', 'product_category.id')
                  ->whereColumn('product_category.game_id', 'games.id')
                  ->where('product_purchases.status', 'success')
                  ->whereMonth('product_purchases.created_at', $now->month)
                  ->whereYear('product_purchases.created_at', $now->year)
                  ->selectRaw('COUNT(*)');
            }, 'total_transaksi')
            ->orderBy('total_transaksi', 'desc')
            ->take(5)
            ->get();

        // ========================================================
        // TAMBAHAN: TRACK DANA (RESET TIAP TANGGAL 1 & BISA DIFILTER)
        // ========================================================
        // Menangkap filter bulan dari URL (jika kosong, gunakan bulan ini)
        $filterBulanDana = $request->input('filter_bulan_dana', $now->format('Y-m'));
        $parsedDate = Carbon::parse($filterBulanDana . '-01');

        $trackModal = ProductPurchases::where('status', 'success')
            ->whereMonth('created_at', $parsedDate->month)
            ->whereYear('created_at', $parsedDate->year)
            ->sum('harga_modal');

        $trackKeuntungan = ProductPurchases::where('status', 'success')
            ->whereMonth('created_at', $parsedDate->month)
            ->whereYear('created_at', $parsedDate->year)
            ->sum('keuntungan_bersih');

        $trackAdmin = ProductPurchases::where('status', 'success')
            ->whereMonth('created_at', $parsedDate->month)
            ->whereYear('created_at', $parsedDate->year)
            ->sum('biaya_admin');

        $trackTotalTransaksi = ProductPurchases::where('status', 'success')
            ->whereMonth('created_at', $parsedDate->month)
            ->whereYear('created_at', $parsedDate->year)
            ->sum('price'); // Uang kotor yang dibayarkan pembeli
        // ========================================================

        return view('admin.index', compact(
            'users', 
            'games', 
            'product', 
            'pembelianMingguIni', 
            'pembelianBulanIni',
            'chartLabels',
            'chartData',
            'visitUserBulanIni',
            'visitGuestBulanIni',
            'topGames',
            // Variabel Track Dana:
            'filterBulanDana',
            'trackModal',
            'trackKeuntungan',
            'trackAdmin',
            'trackTotalTransaksi'
        ));
    }
public function getChartData(Request $request)
    {
        $filter = $request->input('filter', 'bulanan');
        $now = \Carbon\Carbon::now();

        $labels = [];
        $dataTotalNominal = [];
        $dataPembelian = [];
        $dataTopup = [];
        $dataPendaftaran = [];
        $dataPengunjungGame = [];
        $dataPengunjungWeb = [];
        $dataTrxSukses = [];
        $dataTrxBatal = [];
        $dataTrxGagal = [];

        if ($filter == 'mingguan') {
            $startOfWeek = $now->copy()->startOfWeek();
            for ($i = 0; $i < 7; $i++) {
                $date = $startOfWeek->copy()->addDays($i);
                $labels[] = $date->translatedFormat('l'); 
                
                $beli = \App\Models\ProductPurchases::whereDate('created_at', $date)->where('status', 'success')->sum('price');
                $topup = \App\Models\Topup::whereDate('created_at', $date)->where('status', 'success')->sum('amount');
                
                $dataPembelian[] = $beli;
                $dataTopup[] = $topup;
                $dataTotalNominal[] = $beli + $topup; // Gabungan
                
                $dataPendaftaran[] = \App\Models\User::whereDate('created_at', $date)->count();
                $dataPengunjungGame[] = \App\Models\GameVisit::whereDate('created_at', $date)->count();
                // Kunjungan web akan mengambil dari model WebsiteVisit yang akan kita buat
                $dataPengunjungWeb[] = \App\Models\WebsiteVisit::whereDate('created_at', $date)->count(); 
                
                $dataTrxSukses[] = \App\Models\ProductPurchases::where('status', 'success')->whereDate('created_at', $date)->count();
                $dataTrxBatal[] = \App\Models\ProductPurchases::whereIn('status', ['cancel', 'canceled'])->whereDate('created_at', $date)->count();
                $dataTrxGagal[] = \App\Models\ProductPurchases::where('status', 'failed')->whereDate('created_at', $date)->count();
            }
        } elseif ($filter == 'bulanan' || $filter == 'tahunan') {
            $year = $now->year;
            if ($filter == 'tahunan' && $request->has('year')) {
                $year = $request->input('year');
            }

            $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
            foreach ($months as $key => $month) {
                $labels[] = $month;
                $m = $key + 1;
                
                $beli = \App\Models\ProductPurchases::whereMonth('created_at', $m)->whereYear('created_at', $year)->where('status', 'success')->sum('price');
                $topup = \App\Models\Topup::whereMonth('created_at', $m)->whereYear('created_at', $year)->where('status', 'success')->sum('amount');
                
                $dataPembelian[] = $beli;
                $dataTopup[] = $topup;
                $dataTotalNominal[] = $beli + $topup;

                $dataPendaftaran[] = \App\Models\User::whereMonth('created_at', $m)->whereYear('created_at', $year)->count();
                $dataPengunjungGame[] = \App\Models\GameVisit::whereMonth('created_at', $m)->whereYear('created_at', $year)->count();
                $dataPengunjungWeb[] = \App\Models\WebsiteVisit::whereMonth('created_at', $m)->whereYear('created_at', $year)->count();
                
                $dataTrxSukses[] = \App\Models\ProductPurchases::where('status', 'success')->whereMonth('created_at', $m)->whereYear('created_at', $year)->count();
                $dataTrxBatal[] = \App\Models\ProductPurchases::whereIn('status', ['cancel', 'canceled'])->whereMonth('created_at', $m)->whereYear('created_at', $year)->count();
                $dataTrxGagal[] = \App\Models\ProductPurchases::where('status', 'failed')->whereMonth('created_at', $m)->whereYear('created_at', $year)->count();
            }
        } elseif ($filter == 'custom') {
            $startDate = \Carbon\Carbon::parse($request->input('start_date'));
            $endDate = \Carbon\Carbon::parse($request->input('end_date'));

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $labels[] = $date->format('d/m');
                
                $beli = \App\Models\ProductPurchases::whereDate('created_at', $date)->where('status', 'success')->sum('price');
                $topup = \App\Models\Topup::whereDate('created_at', $date)->where('status', 'success')->sum('amount');
                
                $dataPembelian[] = $beli;
                $dataTopup[] = $topup;
                $dataTotalNominal[] = $beli + $topup;

                $dataPendaftaran[] = \App\Models\User::whereDate('created_at', $date)->count();
                $dataPengunjungGame[] = \App\Models\GameVisit::whereDate('created_at', $date)->count();
                $dataPengunjungWeb[] = \App\Models\WebsiteVisit::whereDate('created_at', $date)->count();
                
                $dataTrxSukses[] = \App\Models\ProductPurchases::where('status', 'success')->whereDate('created_at', $date)->count();
                $dataTrxBatal[] = \App\Models\ProductPurchases::whereIn('status', ['cancel', 'canceled'])->whereDate('created_at', $date)->count();
                $dataTrxGagal[] = \App\Models\ProductPurchases::where('status', 'failed')->whereDate('created_at', $date)->count();
            }
        }

        return response()->json([
            'labels' => $labels,
            'total_nominal' => $dataTotalNominal,
            'pembelian' => $dataPembelian,
            'topup' => $dataTopup,
            'pendaftaran' => $dataPendaftaran,
            'pengunjung_game' => $dataPengunjungGame,
            'pengunjung_web' => $dataPengunjungWeb,
            'trx_sukses' => $dataTrxSukses,
            'trx_batal' => $dataTrxBatal,
            'trx_gagal' => $dataTrxGagal,
        ]);
    }
}