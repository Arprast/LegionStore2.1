<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductPurchases;
use App\Models\Wallet;
use App\Models\WalletMutation;

class CallbackController extends Controller
{
    public function omegaReport(Request $request)
    {
        // 1. Catat log untuk melihat format apa yang sebenarnya dikirim OmegaTronik
        \Illuminate\Support\Facades\Log::info('Callback Omegatronik Masuk:', $request->all());

        // 2. TANGKAP NOMOR INVOICE (Filter Format Aneh Omegatronik)
        $orderId = $request->query('refid') ?? $request->query('refID') ?? $request->query('clientid');
        
        // Jika terbaca tulisan "[refid]" karena CS salah setting URL, paksa baca yang huruf kecil
        if ($orderId === '[refid]') {
            $orderId = $request->query('refid');
        }

        // 3. TANGKAP STATUS
        $statusCode = $request->query('status') ?? $request->query('statuscode'); // Menangkap angka "20"
        $keterangan = $request->query('message') ?? $request->query('keterangan');

        // 4. Cari transaksi di database
        $transaksi = ProductPurchases::where('order_id', $orderId)->first();
        if (!$transaksi) {
            return response('OK', 200); 
        }

        // 5. Kamus Kode Error Omegatronik
        $daftarError = [
            '40' => 'Gagal - Ditolak Server Provider',
            '45' => 'Gagal - Stok Produk Kosong',
            '47' => 'Gagal - Produk Sedang Gangguan',
            '50' => 'Gagal - Dibatalkan',
            '52' => 'Gagal - Nomor Tujuan Salah',
            '53' => 'Gagal - Tujuan Diluar Wilayah',
            '55' => 'Gagal - TimeOut (Waktu Habis)',
            '56' => 'Gagal - Nomor Tujuan Ter-Blacklist',
            '69' => 'Gagal - Sistem CutOff / Maintenance',
        ];

        // 6. Proses Update Status
        if (in_array($transaksi->status, ['PROCESSING', 'PAID', 'Sedang Diproses'])) {
            
            // --- JIKA TRANSAKSI SUKSES (Kode 20) ---
            if ($statusCode == '20' || strtolower($statusCode) == 'sukses' || $statusCode == '1') { 
                
                $transaksi->update([
                    'status' => 'SUCCESS',
                    'completed_at' => now(),
                ]);
                
            } 
            // --- JIKA TRANSAKSI PENDING (Kode 2) ---
            elseif ($statusCode == '2') {
                return response('OK', 200); 
            }
            // --- JIKA TRANSAKSI GAGAL ---
            else {
                $alasanGagal = 'Gagal - Sistem Error'; 
                
                if (isset($daftarError[$statusCode])) {
                    $alasanGagal = $daftarError[$statusCode];
                } elseif ($keterangan) {
                    $alasanGagal = $keterangan;
                }

                $transaksi->update([
                    'status' => 'FAILED',
                    'cancel_reason' => $alasanGagal
                ]);
                
                // Refund Saldo
                $wallet = Wallet::where('user_id', $transaksi->user_id)->first();
                if ($wallet) {
                    $wallet->balance += $transaksi->price;
                    $wallet->save();

                    // Catat Mutasi Saldo Masuk (Refund)
                    WalletMutation::create([
                        'user_id' => $transaksi->user_id,
                        'reference_id' => $transaksi->order_id,
                        'type' => 'kredit',
                        'amount' => $transaksi->price,
                        'description' => 'Refund Pembelian (Gagal Provider Omegatronik)'
                    ]);
                }
            }
        }

        return response('OK', 200);
    }
}