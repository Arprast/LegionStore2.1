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

class confirmOrderController extends Controller
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
        $dataProduct = "";
        $totalPrice = 0;
        $buyer_sku_code = "";
        
        // --- VARIABEL UNTUK TRACKING LAPORAN ---
        $hargaModalSistem = 0;
        $keuntunganSistem = 0;
        $biayaAdminSistem = 0;
        // ----------------------------------------------------------

        $userRole = auth()->check() ? auth()->user()->role : 'guest';

        if($req->flash_sale == "0"){
            $dataProduct = Product::with('product_category.game')->where('id',$req->product_id)->first();
            $buyer_sku_code = $dataProduct->buyer_sku_code;
            
            if($dataProduct->active == 0){
                return redirect()->back()->with('error', 'produk tidak aktif');
            }

            // --- KALKULASI DINAMIS BERDASARKAN ROLE ---
            $gameModel = $dataProduct->product_category->game;
            $kalkulasi = $gameModel->kalkulasiHarga($dataProduct->cap_price, $userRole);
            
            $hargaModalSistem = $kalkulasi['harga_modal'];
            $keuntunganSistem = $kalkulasi['keuntungan'];
            $biayaAdminSistem = $kalkulasi['biaya_admin'];
            
            $baseSellingPrice = $kalkulasi['harga_jual'];
            // ------------------------------------------

            if($dataProduct->discount > 0){
                $totalPrice = $baseSellingPrice - $dataProduct->discount;
            } else {
                $totalPrice = $baseSellingPrice;
            }
        }
        else if($req->flash_sale == "1"){
            $FlashSale = FlashSale::find($req->product_id);
            if($FlashSale->stock < 0){
                return redirect()->back()->with('error', 'jumlah tidak cukup');
            }
            if($FlashSale->active == 0){
                return redirect()->back()->with('error', 'promo tidak aktif');
            }
            $dataProduct = FlashSale::with('product','product.product_category.game')->where('id',$req->product_id)->first();
            $buyer_sku_code = $dataProduct->product->buyer_sku_code;
            $totalPrice = $dataProduct->price;
            
            // --- KALKULASI DINAMIS UNTUK FLASH SALE ---
            $gameModel = $dataProduct->product->product_category->game;
            $kalkulasi = $gameModel->kalkulasiHarga($dataProduct->product->cap_price, $userRole);
            
            $hargaModalSistem = $kalkulasi['harga_modal'];
            // Karena Flash Sale menimpa harga jual normal, keuntungannya adalah sisa dari harga promo dikurangi modal & admin
            $keuntunganSistem = $totalPrice - $hargaModalSistem - $kalkulasi['biaya_admin'];
            $biayaAdminSistem = $kalkulasi['biaya_admin'];
            // ----------------------------------------------------------------------------------------------------------

            $FlashSale->stock = $FlashSale->stock - 1;
            $FlashSale->save();
        }

        $invGen = new InvoiceGenerator();
        $nomorInvoiceBaru = $req->flash_sale == "0" ? $invGen->generate($dataProduct->product_category->game->id) : $invGen->generate($dataProduct->product->product_category->game->id);
        
        // 1. BERSIHKAN TANDA PAGAR (#) UNTUK MIDTRANS
        $midtransOrderId = str_replace("#", "", $nomorInvoiceBaru);

        // 2. PASTIKAN HARGA ADALAH INTEGER (Bukan desimal)
        $totalPriceInt = (int) $totalPrice;

        $ProductPurchases = new ProductPurchases;
        $ProductPurchases->product_id = $req->flash_sale == "0" ? $dataProduct->id : $dataProduct->product->id;
        $ProductPurchases->user_id = auth()->check() ? auth()->user()->id : null;
        $ProductPurchases->status = "UNPAID";        
        $ProductPurchases->email = !empty($req->email) ? $req->email : 'guest@example.com';
        $ProductPurchases->no_wa = !empty($req->phone_number) ? $req->phone_number : 'Tidak diisi';
        
        $ProductPurchases->payment_method = 'midtrans_snap'; 
        
        $ProductPurchases->created_at = date('Y-m-d H:i:s');
        $ProductPurchases->completed_at = null;
        $ProductPurchases->name = $req->flash_sale == "0" ? $dataProduct->name : $dataProduct->product->name;
        $ProductPurchases->price = $totalPriceInt; // Gunakan yang sudah Integer
        
        // --- MEREKAM DATA MARGIN KE DATABASE ---
        $ProductPurchases->harga_modal = $hargaModalSistem;
        $ProductPurchases->keuntungan_bersih = $keuntunganSistem;
        $ProductPurchases->biaya_admin = $biayaAdminSistem;
        // ---------------------------------------

        $ProductPurchases->customer_no = $req->account_id.','.$req->server_id;
        $ProductPurchases->source = $req->flash_sale == "0" ? "original" : "flash sale";
        
        $ProductPurchases->order_id = $nomorInvoiceBaru; // Di DB Anda tetap pakai # tidak apa-apa
        
        // Masukkan Order ID yang sudah bersih ke kolom Midtrans
        $ProductPurchases->midtrans_order_id = $midtransOrderId;

        $validEmail = (!empty($req->email) && filter_var($req->email, FILTER_VALIDATE_EMAIL)) ? $req->email : 'guest@example.com';
        $validPhone = !empty($req->phone_number) ? $req->phone_number : '080000000000';

        // --- PERBAIKAN: Buat nama customer lebih dinamis untuk menghindari blokir FDS Midtrans ---
        if (auth()->check()) {
            $customerName = auth()->user()->name;
        } elseif (!empty($req->name)) { 
            $customerName = $req->name;
        } else {
            // Jika tidak ada nama, gunakan "Guest" + 4 digit terakhir order ID agar unik
            $customerName = 'Guest ' . substr($midtransOrderId, -4); 
        }

        $params = [
            'transaction_details' => [
                // GUNAKAN ORDER ID YANG SUDAH BERSIH DARI PAGAR (#)
                'order_id' => $midtransOrderId,
                'gross_amount' => $totalPriceInt, // Gunakan yang sudah Integer
            ],
            'customer_details' => [
                'first_name' => $customerName,
                'email' => $validEmail,
                'phone' => $validPhone,
            ],
            'item_details' => [
                [
                    // Beri fallback 'PROD' jika buyer_sku_code kosong
                    'id' => empty($buyer_sku_code) ? 'PROD' : $buyer_sku_code,
                    'price' => $totalPriceInt,
                    'quantity' => 1,
                    'name' => substr($ProductPurchases->name, 0, 50) 
                ]
            ]
        ];

        // Generate Token Snap dari Midtrans
        $snapToken = Snap::getSnapToken($params);
        
        // Simpan token ke database
        $ProductPurchases->snap_token = $snapToken;
        $ProductPurchases->va_number = "-"; 
        $ProductPurchases->expiry_time = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $ProductPurchases->save();
        
        // Redirect juga pakai ID yang bersih
        return redirect()->to(url('track-progress?inv='.$midtransOrderId)); 
    }
}