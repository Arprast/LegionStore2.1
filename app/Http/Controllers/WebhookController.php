<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductPurchases;
use App\Models\Topup;
use App\Models\Wallet;
use App\Models\WalletMutation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Services\OmegaTronikService;

class WebhookController extends Controller
{
    /**
     * Handle Webhook dari Midtrans
     */
    public function midtrans(Request $req)
    {
        $serverKey = env('MIDTRANS_SERVER_KEY');

        $orderId      = $req->order_id;
        $statusCode   = $req->status_code;
        $grossAmount  = $req->gross_amount;
        $signatureKey = $req->signature_key;

        // 1. Validasi Signature Midtrans
        $mySignature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
        if ($mySignature !== $signatureKey) {
            Log::error('MIDTRANS WEBHOOK: Signature tidak valid!', ['order_id' => $orderId]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        // ================================================================
        // CABANG 1: TOP UP LEGION COIN (Awalan LGN-)
        // ================================================================
        if (str_starts_with($orderId, 'LGN-')) {
            $topup = Topup::where('order_id', $orderId)->first();
            
            if (!$topup) {
                return response()->json(['message' => 'Topup order not found'], 404);
            }

            if ($req->transaction_status == 'settlement' || $req->transaction_status == 'capture') {
                if ($topup->status == 'pending') {
                    $topup->update(['status' => 'success']);
                    
                    $wallet = Wallet::firstOrCreate(
                        ['user_id' => $topup->user_id],
                        ['balance' => 0]
                    );

                    $wallet->increment('balance', $topup->amount);

                    // Catat Mutasi Saldo Masuk (Top Up)
                    WalletMutation::create([
                        'user_id' => $topup->user_id,
                        'reference_id' => $orderId,
                        'type' => 'kredit',
                        'amount' => $topup->amount,
                        'description' => 'Top Up Saldo via Midtrans'
                    ]);

                    Log::info("TOP UP LEGION COIN BERHASIL: $orderId | User: {$topup->user_id} | Amount: {$topup->amount}");
                }
            } 
            elseif (in_array($req->transaction_status, ['expire', 'cancel', 'deny'])) {
                if ($topup->status == 'pending') {
                    $topup->update(['status' => 'failed']);
                }
            }

            return response()->json(['message' => 'Coin Topup Processed Successfully'], 200);
        }

        // ================================================================
        // CABANG 2: PEMBELIAN PRODUK/GAME BIASA (DIGIFLAZZ & OMEGA TRONIK)
        // ================================================================
        
        $order = ProductPurchases::with('products.product_category.game')->where('order_id', $orderId)->first();
        
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Idempotency check
        if (in_array($order->status, ['paid', 'processing', 'success'])) {
            return response()->json(['message' => 'Already Processed'], 200);
        }

        if ($req->transaction_status == 'settlement' || $req->transaction_status == 'capture') {
            $order->update(['status' => 'processing']);

            $provider = strtolower($order->products->product_category->game->provider_transaksi ?? 'digiflazz');

            if ($provider === 'omegatronik') {
                $this->sendToOmegaTronik($order);
            } else {
                $this->sendToDigiflazz($order);
            }

        } 
        elseif (in_array($req->transaction_status, ['expire', 'cancel', 'deny'])) {
            $order->update([
                'status' => 'failed',
                'cancel_reason' => 'Dibatalkan sistem: Pembayaran Midtrans Expired.'
            ]);
        }

        return response()->json(['message' => 'Product Order Processed'], 200);
    }

    /**
     * Kirim Perintah Pembelian ke Omega Tronik
     */
    private function sendToOmegaTronik($order)
    {
        $omegaService = new OmegaTronikService();
        $refId   = $order->order_id;
        $skuCode = $order->products->buyer_sku_code ?? $order->product_id;
        
        // PERBAIKAN: Ambil dari customer_no yang terbukti terisi di database
        $target = $order->customer_no ?? '';
        $cleanTarget = str_replace([',', ' '], '', $target);

        try {
            Log::info("OMEGATRONIK ATTEMPT (MIDTRANS) [$refId]: SKU = $skuCode, Target = $cleanTarget");
            $responseBody = $omegaService->order($skuCode, $cleanTarget, $refId);
            Log::info("OMEGATRONIK RESPONSE (MIDTRANS) [$refId]:", ['body' => $responseBody]);

            if (str_contains($responseBody, 'SUKSES') || str_contains(strtolower($responseBody), 'sedang diproses') || str_contains(strtolower($responseBody), 'akan diproses')) {
                // Biarkan PROCESSING
            } elseif (str_contains($responseBody, 'GAGAL') || str_contains($responseBody, 'Salah') || str_contains($responseBody, 'Gangguan')) {
                $order->update([
                    'status' => 'failed',
                    'cancel_reason' => 'OmegaTronik: ' . substr($responseBody, 0, 100)
                ]);

                // REFUND KE LG-COIN
                $wallet = Wallet::where('user_id', $order->user_id)->first();
                if ($wallet) {
                    $wallet->increment('balance', $order->price);

                    // Catat Mutasi Saldo Masuk (Refund)
                    WalletMutation::create([
                        'user_id' => $order->user_id,
                        'reference_id' => $order->order_id,
                        'type' => 'kredit',
                        'amount' => $order->price,
                        'description' => 'Refund Pembelian (Gagal OmegaTronik)'
                    ]);
                }
            } 

        } catch (\Exception $e) {
            Log::error("KONEKSI OMEGATRONIK ERROR [$refId]: " . $e->getMessage());
        }
    }

    /**
     * Kirim Perintah Pembelian ke Digiflazz
     */
    private function sendToDigiflazz($order)
    {
        $username = env('DIGIFLAZZ_USERNAME'); 
        $apiKey   = env('DIGIFLAZZ_API_KEY');
        $refId    = $order->order_id;

        // PERBAIKAN: Ambil langsung dari customer_no agar tidak kosong lagi
        $target = $order->customer_no ?? '';
        $cleanId = str_replace([',', ' '], '', $target); 

        $skuCode = $order->products->buyer_sku_code ?? $order->product_id;
        $sign = md5($username . $apiKey . $refId);

        if (empty($cleanId)) {
            Log::error("DIGIFLAZZ ABORTED (MIDTRANS) [$refId]: customer_no kosong.");
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

            Log::info("DIGIFLAZZ ATTEMPT (MIDTRANS) [$refId]:", $response->json() ?? []);

            $data = $response->json();
            if(isset($data['data']['status']) && strtolower($data['data']['status']) === 'gagal'){
                $alasan = $data['data']['message'] ?? 'Gagal dari Digiflazz';
                $order->update([
                    'status' => 'failed',
                    'cancel_reason' => 'Gagal sistem: ' . $alasan
                ]);

                // REFUND KE LG-COIN KARENA DITOLAK DIGIFLAZZ DI AWAL
                $wallet = Wallet::where('user_id', $order->user_id)->first();
                if ($wallet) {
                    $wallet->increment('balance', $order->price);

                    // Catat Mutasi Saldo Masuk (Refund)
                    WalletMutation::create([
                        'user_id' => $order->user_id,
                        'reference_id' => $order->order_id,
                        'type' => 'kredit',
                        'amount' => $order->price,
                        'description' => 'Refund Pembelian (Ditolak Digiflazz)'
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('KONEKSI DIGIFLAZZ ERROR (MIDTRANS): ' . $e->getMessage());
        }
    }

    /**
     * Handle Webhook dari Digiflazz (Status akhir sukses/gagal)
     */
    public function digiflazz(Request $request)
    {
        $secret = 'legionstore200111292022031001'; 
        
        $postData = $request->getContent();
        $signatureLocal = 'sha1=' . hash_hmac('sha1', $postData, $secret);
        $signatureDariDigi = $request->header('X-Hub-Signature');

        if ($signatureLocal !== $signatureDariDigi) {
            Log::warning('DIGIFLAZZ WEBHOOK: Signature Tidak Valid!');
            return response()->json(['message' => 'Invalid Signature'], 401);
        }

        $data = json_decode($postData, true);
        $orderId = $data['data']['ref_id'] ?? null;
        $status  = strtolower($data['data']['status'] ?? ''); 

        if ($orderId) {
            $purchase = ProductPurchases::where('order_id', $orderId)->first();

            if ($purchase) {
                if ($status === 'sukses') {
                    $purchase->update([
                        'status' => 'success',
                        'completed_at' => now(),
                        'cancel_reason' => null
                    ]);

                    try {
                        $user = \App\Models\User::find($purchase->user_id);
                        if ($user && $user->email) {
                            $purchase->load('products.product_category.game');
                            $namaGame = $purchase->products->product_category->game->nama ?? 'Game';
                            $namaProduk = $purchase->products->name ?? 'Produk';
                            
                            $isiEmail = "Halo " . $user->name . "!\n\n"
                                      . "Top Up Anda di Legion Store BERHASIL diproses.\n\n"
                                      . "🧾 DETAIL:\n"
                                      . "Order ID: " . $orderId . "\n"
                                      . "Game: " . $namaGame . "\n"
                                      . "Item: " . $namaProduk . "\n"
                                      . "ID: " . $purchase->customer_no . "\n\n"
                                      . "Terima kasih, Bosku! 🔥";

                            \Illuminate\Support\Facades\Mail::raw($isiEmail, function ($message) use ($user, $orderId) {
                                $message->to($user->email)
                                        ->subject("✅ Pesanan Selesai (Order: " . $orderId . ")");
                            });
                        }
                    } catch (\Exception $e) {
                        Log::error("Email Digiflazz Error: " . $e->getMessage());
                    }
                    
                    Log::info("Pesanan Digiflazz $orderId Berhasil.");
                    
                } elseif ($status === 'gagal') {
                    $alasanGagal = $data['data']['sn'] ?? $data['data']['message'] ?? 'Gagal dari server Digiflazz.';
                    $purchase->update([
                        'status' => 'failed',
                        'cancel_reason' => 'Digiflazz: ' . $alasanGagal
                    ]);

                    $wallet = Wallet::where('user_id', $purchase->user_id)->first();
                    if ($wallet) {
                        $wallet->increment('balance', $purchase->price);

                        // Catat Mutasi Saldo Masuk (Refund)
                        WalletMutation::create([
                            'user_id' => $purchase->user_id,
                            'reference_id' => $purchase->order_id,
                            'type' => 'kredit',
                            'amount' => $purchase->price,
                            'description' => 'Refund Pembelian (Gagal Sistem Digiflazz)'
                        ]);
                    }

                    Log::error("Pesanan Digiflazz $orderId Gagal: " . $alasanGagal . ". Refunded to LG-Coin.");
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}