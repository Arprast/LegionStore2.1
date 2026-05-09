<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FlashSale;
use App\Models\Game;
use App\Models\Product;
use App\Models\Faq;
use App\Models\NumberCode;
use App\Models\GameVisit; 
use App\Services\OmegaTronikService; 

class ProductListController extends Controller
{
    public function index($identifier)
    {
        $data = Game::with(['product_category','product_category.products'])
                    ->where('slug', $identifier)
                    ->first();

        if (!$data) {
            $data = Game::with(['product_category','product_category.products'])
                        ->where('id', $identifier)
                        ->firstOrFail();
        }

        $game_id = $data->id;

        // =========================================================
        // TAMBAHAN: LOOPING KALKULASI HARGA DINAMIS BERDASARKAN ROLE
        // =========================================================
        $userRole = auth()->check() ? auth()->user()->role : 'guest';
        
        if($data && $data->product_category) {
            foreach($data->product_category as $category) {
                foreach($category->products as $product) {
                    $kalkulasi = $data->kalkulasiHarga($product->cap_price, $userRole);
                    
                    // Cek apakah $kalkulasi berbentuk array atau langsung angka
                    if (is_array($kalkulasi)) {
                        $product->selling_price = $kalkulasi['harga_jual']; 
                    } else {
                        $product->selling_price = $kalkulasi; 
                    }
                }
            }
        }
        // =========================================================

        $flash_sale = \App\Models\FlashSale::whereHas('product.product_category.game', function($q) use ($game_id){
            $q->where('id',$game_id);
        })->with('product')->get();

        $faq = \App\Models\Faq::where('game_id',$game_id)->get();
        $numberCode = \App\Models\NumberCode::all();

        $account_id_method = isset($data->account_id_method) ? $data->account_id_method : 'User ID';
        $server_input_method = isset($data->server_input_method) ? $data->server_input_method : 'manual';
        $regions = (isset($data->regions) && $data->regions != null) ? (is_array($data->regions) ? $data->regions : json_decode($data->regions, true)) : [];

        \App\Models\GameVisit::create([
            'game_id' => $data->id,
            'user_id' => auth()->check() ? auth()->id() : null,
            'ip_address' => request()->ip()
        ]);

        $ratingStats = \App\Models\Rating::whereHas('product_purchases.products.product_category', function($q) use ($game_id){
            $q->where('game_id', $game_id);
        })
        ->selectRaw('AVG(rating) as avg_rating, COUNT(id) as total_reviews')
        ->first();

        $avgRating = $ratingStats->avg_rating ? number_format($ratingStats->avg_rating, 1) : '5.0';
        $totalReviews = $ratingStats->total_reviews ? $ratingStats->total_reviews : 0;

        $latestReviews = \App\Models\Rating::with('product_purchases.users', 'product_purchases.products')
            ->whereHas('product_purchases.products.product_category', function($q) use ($game_id){
                $q->where('game_id', $game_id);
            })
            ->whereNotNull('reviews')
            ->where('reviews', '!=', '')
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        return view('product_list', compact(
            'data',
            'flash_sale',
            'faq',
            'numberCode',
            'account_id_method',
            'server_input_method',
            'regions',
            'avgRating',        
            'totalReviews',     
            'latestReviews'     
        ));
    }

    public function searchProduct($identifier, $id) 
    {
        try {
            $product = \App\Models\Product::with('product_category.game')->findOrFail($id);
            
            // =========================================================
            // TAMBAHAN: KALKULASI SAAT USER MENGKLIK PRODUK (POPUP AJAX)
            // =========================================================
            $game = $product->product_category->game;
            $userRole = auth()->check() ? auth()->user()->role : 'guest';
            
            $kalkulasi = $game->kalkulasiHarga($product->cap_price, $userRole);
            
            // Cek apakah $kalkulasi berbentuk array atau langsung angka
            if (is_array($kalkulasi)) {
                $product->selling_price = $kalkulasi['harga_jual'];
            } else {
                $product->selling_price = $kalkulasi;
            }
            // =========================================================

            return response()->json(['status' => 'success', 'data' => $product], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Produk tidak ditemukan'], 404);
        }
    }

    public function searchFlashSale($identifier, $id) 
    {
        try {
            $flashSale = \App\Models\FlashSale::with('product.product_category.game')->findOrFail($id);
            return response()->json(['status' => 'success', 'data' => $flashSale], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Promo Flash Sale tidak ditemukan'], 404);
        }
    }
    
    public function checkNickname(Request $request)
    {
        $game_id = $request->game_id;
        $accountId = $request->account_id;
        $serverId = $request->server_id;

        $game = Game::find($game_id);
        
        if (!$game || $game->is_cek_id_aktif == 0) {
            return response()->json(['status' => 'error', 'message' => 'Fitur cek ID tidak aktif']);
        }

        if ($game->provider_cek_id === 'omegatronik') {
            $key = '5z7BT5k6Yh';
            $slug = $game->kode_cek_id; 

            if (empty($slug)) {
                return response()->json(['status' => 'error', 'message' => 'Kode Cek ID belum diatur di Admin Panel']);
            }

            $url = "https://api.omegatronik.co.id/api/game/{$slug}?id={$accountId}&key={$key}";
            if (!empty($serverId)) {
                $url .= "&zone={$serverId}";
            }

            try {
                $client = new \GuzzleHttp\Client();
                $response = $client->request('GET', $url, ['http_errors' => false]);
                $body = json_decode($response->getBody(), true);

                if ($response->getStatusCode() == 200) {
                    if (isset($body['status']) && $body['status'] === false) {
                         return response()->json(['status' => 'error', 'message' => $body['message'] ?? 'ID tidak ditemukan.']);
                    }
                    $nickname = $body['name'] ?? $body['username'] ?? $body['nickname'] ?? ($body['data']['name'] ?? ($body['data']['username'] ?? null));
                    
                    if ($nickname && $nickname != '') {
                        return response()->json(['status' => 'success', 'nickname' => urldecode($nickname)]);
                    }
                }
                
                $errorMsg = $body['message'] ?? 'ID tidak ditemukan atau server salah.';
                return response()->json(['status' => 'error', 'message' => $errorMsg]);
                
            } catch (\Exception $e) {
                return response()->json(['status' => 'error', 'message' => 'Server Gangguan']);
            }
        }

        return response()->json(['status' => 'error', 'message' => 'Provider belum disupport']);
    }
    
    public function callbackOmega(Request $request)
    {
        $refId  = $request->input('refID');
        $status = $request->input('status'); 
        $sn     = $request->input('sn');     
        $ket    = $request->input('keterangan'); 

        if (!$refId) {
            return response('Parameter refID kosong', 400);
        }

        $order = \App\Models\ProductPurchases::where('order_id', $refId)->first();

        if (!$order) {
            return response('Pesanan tidak ditemukan', 404);
        }

        if (in_array($order->status, ['success', 'failed'])) {
            return response('OK', 200);
        }

        if ($status == '20' || strtoupper($status) === 'SUKSES') {
            
            $order->update([
                'status' => 'success',
                'completed_at' => now(),
                'cancel_reason' => $sn ? 'SN: ' . $sn : null
            ]);

            try {
                $user = \App\Models\User::find($order->user_id);
                if ($user && $user->email) {
                    $namaGame = $order->products->product_category->game->nama ?? 'Game';
                    $namaProduk = $order->products->name ?? 'Produk';
                    
                    $isiEmail = "Halo " . $user->name . "!\n\n"
                              . "Top Up Anda di Legion Store BERHASIL diproses.\n\n"
                              . "🧾 DETAIL:\n"
                              . "Order ID: " . $order->order_id . "\n"
                              . "Game: " . $namaGame . "\n"
                              . "ID: " . $order->account_id . " " . $order->zone . "\n\n"
                              . "Terima kasih, Bosku! 🔥";

                    \Illuminate\Support\Facades\Mail::raw($isiEmail, function ($message) use ($user, $order) {
                        $message->to($user->email)
                                ->subject("✅ Pesanan Selesai (Order: " . $order->order_id . ")");
                    });
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Email Omega Error: " . $e->getMessage());
            }

            \Illuminate\Support\Facades\Log::info("Callback Omega SUKSES [$refId]");
            \Illuminate\Support\Facades\Log::info("Callback Omega SUKSES [$refId] - SN: $sn");

        } 
        elseif ($status == '2' || strtoupper($status) === 'GAGAL') {
            
            $alasan = $ket ?? 'Gagal dari server Omega Tronik';
            $order->update([
                'status' => 'failed',
                'cancel_reason' => 'Refund: ' . $alasan
            ]);

            $wallet = \App\Models\Wallet::firstOrCreate(
                ['user_id' => $order->user_id],
                ['balance' => 0]
            );
            $wallet->increment('balance', $order->price);

            \Illuminate\Support\Facades\Log::warning("Callback Omega GAGAL [$refId] - Ket: $alasan. Refunded to LG-Coin.");
        }

        return response('OK', 200);
    }
}