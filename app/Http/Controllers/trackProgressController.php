<?php

namespace App\Http\Controllers;

use App\Services\InvoiceGenerator;
use Illuminate\Http\Request;
use App\Models\FlashSale;
use App\Models\Game;
use App\Models\Product;
use App\Models\ProductPurchases;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\CoreApi;

class trackProgressController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function index(Request $req)
    {
        if($req->inv!=null){
            return redirect()->to(url('track-progress/detail?inv='.$req->inv));
        }
        return view('track_progress');
        // return "halaman track progress";
    }

    public function detail(Request $req)
    {
        // PERUBAHAN: Menambahkan 'ratings' di dalam with()
        $data = ProductPurchases::with('products','users','products.flash_sales','products.product_category.game', 'ratings')
            ->where('order_id',$req->inv)
            ->first();
            
        // return response()->json($data);
        $va_number = null;
        $bank = null;
        
        if($data==null){
            return redirect()->to(url('track-progress'))->with('error','Invoice tidak ditemukan');
        }
        
        // $order_id = $data->order_id;
        // $gross_amount = $data->price;

        // $params_qris = [
        //     'transaction_details' => [
        //         'order_id' => $order_id,
        //         'gross_amount' => $gross_amount,
        //     ],
        //     'customer_details' => [
        //         'first_name' => $data->email,
        //         'email' => $data->email,
        //         'phone' => $data->no_wa,
        //     ],
        //     'item_details' => [
        //         [
        //             'id' => $data->products->buyer_sku_code,
        //             'price' => $gross_amount,
        //             'quantity' => 1,
        //             'name' => $data->name
        //         ]
        //     ],
        //     // optional: supaya lebih fokus QRIS
        //     // 'enabled_payments' => ['other_qris','BRI VA','BNI VA','BCA VA']
        // ];
        // $snapToken = Snap::getSnapToken($params_qris);
        
        $snapToken = $data->snap_token;
        return view('order_detail',compact('data','snapToken'));
    }

    public function completePayment(Request $req)
    {
        // $data=ProductPurchases::with('products','products.flash_sales','products.game')->where('order_id',"#".$req->inv)->first();
        // return response()->json($data);
        return view('complete_payment');
    }

    // =======================================================
    // TAMBAHAN BARU: FUNGSI UNTUK SUBMIT RATING DARI MODAL
    // =======================================================
    public function submitRating(Request $req)
    {
        // Validasi input
        $req->validate([
            'product_purchase_id' => 'required',
            'rating' => 'required|integer|min:1|max:5',
            'reviews' => 'nullable|string'
        ]);

        // Cek apakah sudah pernah ulas (biar tidak double input)
        $existing = \App\Models\Rating::where('product_purchase_id', $req->product_purchase_id)->first();
        if($existing) {
            return back();
        }

        // Simpan ke DB (Pastikan di array $fillable model Rating menggunakan 'reviews' bukan 'revies')
        \App\Models\Rating::create([
            'product_purchase_id' => $req->product_purchase_id,
            'rating' => $req->rating,
            'reviews' => $req->reviews,
            'created_at' => time() // Format timestamp
        ]);

        return back(); // Kembali ke halaman tadi secara diam-diam
    }
}