<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Topup;
use App\Models\Voucher;
use App\Models\WalletMutation;

class LegionCoinController extends Controller
{
    // 1. Fungsi memproses Top Up dari Modal
    public function storeTopup(Request $request) 
    {
        $request->validate(['amount' => 'required|numeric|min:10000']);

        $orderId = 'LGN-' . time();
        $amount = $request->amount;

        Topup::create([
            'order_id' => $orderId,
            'user_id' => auth()->id(),
            'amount' => $amount,
            'status' => 'pending'
        ]);

        \Midtrans\Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => $amount,
            ],
            'customer_details' => [
                'first_name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ],
        ];

        $snapToken = \Midtrans\Snap::getSnapToken($params);
        return response()->json(['snap_token' => $snapToken]);
    }

    // 2. Fungsi Webhook dari Midtrans (Menambah Saldo Otomatis)
    public function midtransCallback(Request $request) 
    {
        $serverKey = env('MIDTRANS_SERVER_KEY');
        $hashed = hash("sha512", $request->order_id . $request->status_code . $request->gross_amount . $serverKey);

        if ($hashed == $request->signature_key) {
            if ($request->transaction_status == 'capture' || $request->transaction_status == 'settlement') {
                $topup = Topup::where('order_id', $request->order_id)->first();
                
                if ($topup && $topup->status == 'pending') {
                    // Ubah status sukses
                    $topup->update(['status' => 'success']);

                    // Tambah Koin ke Dompet
                    $wallet = Wallet::firstOrCreate(['user_id' => $topup->user_id]);
                    $wallet->increment('balance', $topup->amount);

                    // Catat Mutasi Saldo Masuk (Top Up)
                    WalletMutation::create([
                        'user_id' => $topup->user_id,
                        'reference_id' => $request->order_id,
                        'type' => 'kredit',
                        'amount' => $topup->amount,
                        'description' => 'Top Up Saldo via Midtrans'
                    ]);
                }
            }
        }
        return response()->json(['message' => 'Callback received']);
    }

    // 3. Fungsi Redeem Voucher Promo
    public function redeemVoucher(Request $request) 
    {
        $request->validate(['code' => 'required']);

        $voucher = Voucher::where('code', $request->code)
                          ->where('expires_at', '>', now())
                          ->first();

        if (!$voucher || $voucher->used_count >= $voucher->usage_limit) {
            return back()->with('error', 'Kode voucher tidak valid atau sudah habis kuotanya.');
        }

        // Tambah koin dari voucher
        $wallet = Wallet::firstOrCreate(['user_id' => auth()->id()]);
        $wallet->increment('balance', $voucher->amount);
        $voucher->increment('used_count');

        // Catat Mutasi Saldo Masuk (Voucher)
        WalletMutation::create([
            'user_id' => auth()->id(),
            'reference_id' => 'VOUCHER-' . $voucher->code,
            'type' => 'kredit',
            'amount' => $voucher->amount,
            'description' => 'Klaim Voucher Promo'
        ]);

        return back()->with('success', 'Berhasil! Anda mendapatkan ' . number_format($voucher->amount) . ' Legion Coin.');
    }
}