<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ProductCategory;
use App\Models\Faq;

class Game extends Model
{
    use HasFactory;

    protected $table = 'games';
    protected $primaryKey = 'id';
    public $incrementing = true;
    public $timestamps = false;
    protected $keyType = 'integer';
    
    protected $casts = [
        'regions' => 'array',
        'is_cek_id_aktif' => 'boolean', // Mengubah output database 0/1 menjadi true/false
    ];
    
    protected $fillable = [
        'id',
        'name',
        'slug',
        'image',
        'letter_logo',
        'banner',
        'category',
        
        // --- SESUAI DENGAN TABEL ANDA YANG SUDAH ADA ---
        'tipe_profit',
        'profit_guest',
        'profit_customer',
        'profit_membership',
        'profit_mitra',
        // ------------------------------------------------

        'is_trending',
        'account_id_method',
        'server_input_method',
        'regions',
        'provider_transaksi',
        'is_cek_id_aktif',
        'provider_cek_id',
        'kode_cek_id'
    ];

    // ======================================================
    // FUNGSI BOOT UNTUK MEMBUAT SLUG OTOMATIS
    // ======================================================
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($game) {
            if (empty($game->slug)) {
                $game->slug = Str::slug($game->name);
            }
        });

        static::updating(function ($game) {
            if (empty($game->slug)) {
                $game->slug = Str::slug($game->name);
            }
        });
    }

    public function product_category()
    {
        return $this->hasMany(
            ProductCategory::class,
            'game_id', 
            'id'  
        );
    }
    
    public function faq()
    {
        return $this->hasMany(
            Faq::class,
            'game_id', 
            'id'  
        );
    }

  // =======================================================
    // FUNGSI MENGHITUNG HARGA BERDASARKAN TIPE (PERSEN/NOMINAL)
    // =======================================================
    public function kalkulasiHarga($harga_modal, $userRole = null)
    {
        // 1. Cek role pengguna yang sedang login
        $role = $userRole ?? (auth()->check() ? auth()->user()->role : 'guest');

        // 2. Ambil nilai profit sesuai role dari database
        switch ($role) {
            case 'supervisor': // <-- Ditambahkan ke sini
            case 'admin':      // <-- Ditambahkan ke sini
            case 'mitra':
                // Admin, Supervisor, dan Mitra mendapatkan harga (margin) yang SAMA
                $nilai_profit = $this->profit_mitra ?? 0;
                break;
            case 'membership':
                $nilai_profit = $this->profit_membership ?? 0;
                break;
            case 'customer':
                $nilai_profit = $this->profit_customer ?? 0;
                break;
            default: // guest / belum login
                $nilai_profit = $this->profit_guest ?? 0;
                break;
        }

        // 3. Kalkulasi margin (keuntungan bersih) berdasarkan Tipe Profit
        $margin = 0;
        
        // KODE INI DIHAPUS/DIUBAH KARENA ADMIN/SPV SEKARANG DAPAT MARGIN
        // (Ubah langsung bagian if ini saja)
        if ($this->tipe_profit == 'persen') {
            // Rumus Persentase: (Nilai Profit / 100) * Harga Modal
            $margin = ($nilai_profit / 100) * $harga_modal;
        } else {
            // Rumus Nominal: Tambahkan langsung angka profitnya
            $margin = $nilai_profit;
        }

        // ==========================================
        // 4. HITUNG BIAYA ADMIN (DIAMBIL DARI APP CONFIG)
        // ==========================================
        $biaya_admin_rupiah = 0;
        
        // Cek dulu apakah ada tabel AppConfig dan ambil datanya
        $appConfig = \App\Models\AppConfig::first(); 
        
        if ($appConfig && $appConfig->biaya_admin > 0) {
            // Rumus: (Persen Biaya Admin / 100) * Harga Modal
            $biaya_admin_rupiah = ($appConfig->biaya_admin / 100) * $harga_modal;
        }
        // ==========================================

        // 5. Kembalikan harga akhir (Modal + Keuntungan + Biaya Admin)
        $harga_jual = round($harga_modal + $margin + $biaya_admin_rupiah);

        // KEMBALIKAN DALAM BENTUK ARRAY
        return [
            'harga_modal' => $harga_modal,
            'keuntungan'  => round($margin),
            'biaya_admin' => round($biaya_admin_rupiah), // Biaya admin sekarang masuk sini!
            'harga_jual'  => $harga_jual,
        ];
    }
}