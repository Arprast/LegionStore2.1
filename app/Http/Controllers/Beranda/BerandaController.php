<?php

namespace App\Http\Controllers\Beranda;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FlashSale;
use App\Models\Carousel;
use App\Models\Game;
use App\Models\Product;
use App\Models\AppConfig;
use App\Models\SpecialOffers;
use App\Models\WebsiteVisit;

class BerandaController extends Controller
{
    public function index()
    {
        // ==========================================
        // MULAI: REKAM DATA KUNJUNGAN WEBSITE
        // ==========================================
        WebsiteVisit::create([
            'ip_address' => request()->ip(),
            'user_id' => auth()->check() ? auth()->id() : null
        ]);
        // ==========================================

        // ==========================================
        // PERBAIKAN: Kita cukup filter sampai "product" saja agar sistem tidak crash.
        // Jika gamenya kosong, otomatis akan disembunyikan oleh Blade view.
        // ==========================================
        $flash_sale = FlashSale::with('product.product_category.game')
            ->whereHas('product') // <-- Diubah: Hanya pastikan produknya tidak terhapus
            ->get();
            
        $carousels = Carousel::all();
        $games = Game::all();
        $app_configs = AppConfig::first();
        
        // ==========================================
        // TAMBAHAN UNTUK FIX TAMPILAN FLASH DEAL
        // ==========================================
        $data = $flash_sale; 
        
        $flash_end = $app_configs ? $app_configs->flash_sale_end : null;
        
        return view('home', compact('flash_sale', 'carousels', 'games', 'app_configs', 'data', 'flash_end'));
    }

public function searchProductsAndGame(Request $req)
    {
        // 1. Bersihkan dan kecilkan huruf inputan user
        $keyword = strtolower(trim($req->q));

        if (empty($keyword)) {
            return response()->json(['data' => []]);
        }

        // 2. Ambil semua data game dari database
        $allGames = Game::all();

        // 3. Filter data: Cari kecocokan di NAMA ASLI atau SINGKATANNYA
        $filteredGames = $allGames->filter(function($game) use ($keyword) {
            $nameLower = strtolower($game->name);

            // A. Pengecekan Nama Keseluruhan (Seperti LIKE %keyword%)
            if (str_contains($nameLower, $keyword)) {
                return true;
            }

            // B. Membuat Singkatan Dinamis (Huruf pertama dari tiap kata)
            // Memisahkan kata berdasarkan spasi, tanda strip (-), atau simbol lain
            $words = preg_split('/[^a-z0-9]/', $nameLower, -1, PREG_SPLIT_NO_EMPTY);
            $acronym = '';
            
            foreach ($words as $word) {
                $acronym .= $word[0]; // Ambil karakter paling depan
            }

            // C. Cek apakah ketikan user cocok dengan singkatan
            // str_starts_with memungkinkan user mengetik "ml" dan tetap menemukan "mlbb"
            if ($keyword === $acronym || str_starts_with($acronym, $keyword)) {
                return true;
            }

            return false;
        });

        // 4. Proses data untuk respons JSON (Mapping gambar)
        $data = [];
        foreach($filteredGames as $game){
            $game->imagePath = asset('storage/game/'.$game->image);
            $data[] = $game; // Masukkan ke array baru agar key array berurutan
        }

        return response()->json(compact('data'));
    }

    public function getSpecialOffer()
    {
        $data = SpecialOffers::orderBy('id', 'desc')->where('is_active', 1)->first();
        if($data != null){
            $data->imagePath = asset('storage/special_offer/'.$data->image);
        }
        return response()->json(compact('data'), 200);
    }
}