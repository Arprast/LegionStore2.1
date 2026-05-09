<?php

namespace App\Http\Controllers;

use App\Models\ProductPurchases;
use App\Models\Wallet;
use App\Models\Product;
use App\Models\WalletMutation; // <--- TAMBAHAN: Import Model Mutasi
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\OmegaTronikService;

class TransactionController extends Controller
{
    public function checkStatus($invoice)
    {
        $transaksi = DB::table('product_purchases')->where('order_id', $invoice)->first();
        if ($transaksi) {
            return response()->json(['status' => $transaksi->status]);
        }
        return response()->json(['status' => 'Tidak Ditemukan'], 404);
    }

    public function payWithWallet($invoice)
    {
        $user = auth()->user();
        
        DB::beginTransaction();
        try {
            // 1. Kunci baris transaksi agar tidak ada double click
            $transaksi = ProductPurchases::with('products')->where('order_id', $invoice)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$transaksi) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'message' => 'Pesanan tidak ditemukan'], 404);
            }

            if ($transaksi->status !== 'UNPAID') {
                DB::rollBack();
                return response()->json(['status' => 'error', 'message' => 'Pesanan sudah diproses sebelumnya'], 400);
            }

            // [PERBAIKAN]: Cek apakah customer_no kosong sebelum saldo dipotong
            if (empty(trim($transaksi->customer_no))) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'message' => 'Nomor tujuan (customer_no) kosong di database. Silakan buat pesanan ulang.'], 400);
            }

            // 2. Cek Saldo Wallet (LG-COIN)
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            $saldo = $wallet ? $wallet->balance : 0;

            if ($saldo < $transaksi->price) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'message' => 'Saldo LG-Coin tidak mencukupi'], 400);
            }

            // 3. Potong Saldo Pembeli (Uang dihilangkan di awal)
            $wallet->balance = $saldo - $transaksi->price;
            $wallet->save();

            // --- TAMBAHAN: Catat Mutasi Saldo Keluar (Pembelian) ---
            WalletMutation::create([
                'user_id' => $user->id,
                'reference_id' => $transaksi->order_id,
                'type' => 'debit',
                'amount' => $transaksi->price,
                'description' => 'Pembelian ' . ($transaksi->products->name ?? 'Produk Game')
            ]);
            // --------------------------------------------------------

            // 4. Ubah Status Pesanan Menjadi PROCESSING
            $transaksi->status = 'processing';
            $transaksi->payment_method = 'LG-COIN';
            $transaksi->save();

            // 5. Simpan perubahan ke Database (Saldo fix terpotong)
            DB::commit();

            // =======================================================
            // 6. EKSEKUSI API KE PROVIDER (DIGIFLAZZ / OMEGA)
            // =======================================================
            $transaksi->load('products.product_category.game');
            $provider = strtolower($transaksi->products->product_category->game->provider_transaksi ?? 'digiflazz');

            if ($provider === 'omegatronik') {
                $this->sendToOmegaTronik($transaksi);
            } else {
                $this->sendToDigiflazz($transaksi);
            }

            return response()->json(['status' => 'success', 'message' => 'Pembayaran berhasil! Pesanan sedang diproses.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Wallet Payment Error [$invoice]: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Sistem Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * FUNGSI TEMBAK OMEGA TRONIK
     */
    private function sendToOmegaTronik($order)
    {
        $omegaService = new OmegaTronikService();
        $refId   = $order->order_id;
        $skuCode = $order->products->buyer_sku_code ?? $order->product_id;
        
        // [PERBAIKAN]: Ambil langsung dari customer_no yang terisi di database
        $target = $order->customer_no ?? '';
        
        // Bersihkan koma dan spasi (Misal: "137154792,2694" menjadi "1371547922694")
        $cleanTarget = str_replace([',', ' '], '', $target);

        try {
            Log::info("OMEGATRONIK ATTEMPT (LG-COIN) [$refId]: SKU = $skuCode, Target = $cleanTarget");
            $responseBody = $omegaService->order($skuCode, $cleanTarget, $refId);
            Log::info("OMEGATRONIK RESPONSE (LG-COIN) [$refId]:", ['body' => $responseBody]);

            if (str_contains($responseBody, 'SUKSES') || str_contains(strtolower($responseBody), 'sedang diproses') || str_contains(strtolower($responseBody), 'akan diproses')) {
                // Berhasil dikirim, status tetap PROCESSING, tunggu Callback dari Omega
            } elseif (str_contains($responseBody, 'GAGAL') || str_contains($responseBody, 'Salah') || str_contains($responseBody, 'Gangguan')) {
                // Gagal dari awal (Provider menolak)
                $order->update([
                    'status' => 'failed',
                    'cancel_reason' => 'OmegaTronik: ' . substr($responseBody, 0, 100)
                ]);

                // REFUND SALDO LG-COIN KARENA GAGAL
                $wallet = Wallet::where('user_id', $order->user_id)->first();
                if ($wallet) {
                    $wallet->increment('balance', $order->price);

                    // --- TAMBAHAN: Catat Mutasi Saldo Masuk (Refund) ---
                    WalletMutation::create([
                        'user_id' => $order->user_id,
                        'reference_id' => $order->order_id,
                        'type' => 'kredit',
                        'amount' => $order->price,
                        'description' => 'Refund Pembelian (Gagal Awal Omegatronik)'
                    ]);
                    // ---------------------------------------------------
                }
            } 
        } catch (\Exception $e) {
            Log::error("KONEKSI OMEGATRONIK ERROR (LG-COIN) [$refId]: " . $e->getMessage());
        }
    }

    /**
     * FUNGSI TEMBAK DIGIFLAZZ
     */
    private function sendToDigiflazz($order)
    {
        $username = env('DIGIFLAZZ_USERNAME'); 
        $apiKey   = env('DIGIFLAZZ_API_KEY');
        $refId    = $order->order_id;

        // [PERBAIKAN]: Ambil langsung dari customer_no yang terisi di database
        $target = $order->customer_no ?? '';
        
        // Bersihkan koma dan spasi (Misal: "137154792,2694" menjadi "1371547922694")
        $cleanId = str_replace([',', ' '], '', $target); 

        $skuCode = $order->products->buyer_sku_code ?? $order->product_id;
        $sign = md5($username . $apiKey . $refId);

        // Jika cleanId kosong, batalkan eksekusi API agar log tidak dipenuhi error Digiflazz
        if (empty($cleanId)) {
            Log::error("DIGIFLAZZ ABORTED [$refId]: Nomor Tujuan (customer_no) kosong setelah dibersihkan.");
            return;
        }

        try {
            $response = Http::post('https://api.digiflazz.com/v1/transaction', [
                'username'       => $username,
                'buyer_sku_code' => $skuCode,
                'customer_no'    => $cleanId, 
                'ref_id'         => $refId,
                'sign'           => $sign,
                'allow_dot'      => false 
            ]);

            Log::info("DIGIFLAZZ ATTEMPT (LG-COIN) [$refId]:", $response->json() ?? []);

            $data = $response->json();
            if(isset($data['data']['status']) && strtolower($data['data']['status']) === 'gagal'){
                $alasan = $data['data']['message'] ?? 'Gagal dari Digiflazz';
                $order->update([
                    'status' => 'failed',
                    'cancel_reason' => 'Gagal Digiflazz: ' . $alasan
                ]);

                // REFUND SALDO LG-COIN KARENA GAGAL
                $wallet = Wallet::where('user_id', $order->user_id)->first();
                if ($wallet) {
                    $wallet->increment('balance', $order->price);

                    // --- TAMBAHAN: Catat Mutasi Saldo Masuk (Refund) ---
                    WalletMutation::create([
                        'user_id' => $order->user_id,
                        'reference_id' => $order->order_id,
                        'type' => 'kredit',
                        'amount' => $order->price,
                        'description' => 'Refund Pembelian (Ditolak Awal Digiflazz)'
                    ]);
                    // ---------------------------------------------------
                }
            } else {
                // Berhasil dikirim, status tetap PROCESSING, nunggu Webhook Digiflazz
            }

        } catch (\Exception $e) {
            Log::error('KONEKSI DIGIFLAZZ ERROR (LG-COIN): ' . $e->getMessage());
        }
    }
}