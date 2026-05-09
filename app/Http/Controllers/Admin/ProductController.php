<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Game;
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;

class ProductController extends Controller
{
    public function index($game_id){
        $data=Game::where('id', $game_id)->first();
        return view('admin.product.index',compact('data','game_id'));
    }

    public function data($game_id)
    {
        // Menambahkan with('product_category.game') agar tidak memberatkan database saat melooping harga
        return DataTables::of(Product::with('product_category.game')->whereHas('product_category.game', function($query) use($game_id) {
            $query->where('id', $game_id);
        }))
            ->addIndexColumn()
            ->addColumn('selling_price', function ($row) {
                // Menghitung harga jual dinamis khusus role 'guest' untuk ditampilkan di tabel admin
                $game = $row->product_category->game;
                $kalkulasi = $game->kalkulasiHarga($row->cap_price, 'guest');
                return 'Rp ' . number_format($kalkulasi['harga_jual'], 0, ',', '.');
            })
            ->addColumn('action', function ($row) use($game_id) {
                $actionButton='<div class="btn-group" style="margin-bottom:5px">';
                $actionButton.=$row->active=='0'?
                '<a data-id="'.$row->id.'" class="btn btn-xs btn-success switch-btn">Aktifkan</a>'
                :
                '<a data-id="'.$row->id.'" class="btn btn-xs btn-default switch-btn">Nonktifkan</a>'
                ;
                $actionButton.='</div>';
                $actionButton.='<div class="btn-group">';
                $actionButton.='                  
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-warning edit-btn">Edit</a>
                ';
                
                $actionButton.='<a data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn">Hapus</a>
                  </div>';
                return $actionButton;
            })
            ->rawColumns(['action', 'selling_price'])
            ->make(true);
    }

    public function getOne($game_id,$id){
      $data=Product::find($id);
      if(!$data){
        return response()->json([
                'status' => 'error',
                'message' => 'Data Tidak Ada']
            , 404);
      }
      $data->imagePath=asset('storage/product/'.$data->image);
      return response()->json([
          'status' => 'success',
          'data'=>$data]
      , 200); 
    }

    public function prosesAdd(Request $req,$game_id){
        // VALIDASI: selling-price dihapus
        $validator = Validator::make($req->all(),[
            'name' => 'required',
            'cap-price' => 'required|integer',
            'sku_code' => 'required',
            "buyer_product_status" => "required|in:0,1",
            "seller_product_status" => "required|in:0,1",
          ]);
      
          if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
          }
          $cekGame = Game::where('id', $game_id)->first();
          if (!$cekGame) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ['name'=>['Game tidak ada']]
            ], 404);
          }
          $cekProduk = Product::where('name', $req->name)->where('product_category_id', $req->category_id)->first();
          if ($cekProduk) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ['name'=>['Produk sudah ada']]
            ], 422);
          }
          $imagename=Str::random(5);
          $Product=new Product;
          $Product->product_category_id=$req->category_id;
          $Product->name=$req->name;
          $Product->cap_price=$req['cap-price'];
          
          // SET DEFAULT HARGA JUAL KE 0 KARENA SUDAH OTOMATIS
          $Product->selling_price = 0; 
          
          $Product->discount=$req->discount??0;
          $Product->buyer_sku_code=$req->sku_code;
          $Product->buyer_product_status=$req->buyer_product_status;
          $Product->seller_product_status=$req->seller_product_status;
          if($req->image!=NULL){
            $mimetype = $req->image->getMimeType();
            $Product->image=$imagename.".".explode('/',$mimetype)[1];
            $upload=$req->file('image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/product', $Product->image);
          }
          $simpan=$Product->save();
          if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Produk berhasil disimpan',
                'data' => $Product
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal menyimpan produk'
          ], 500);
          
    }

    public function prosesEdit(Request $req,$game_id,$id){
      // VALIDASI: selling-price dihapus
      $validator = Validator::make($req->all(),[
          'name' => 'required',
          'cap-price' => 'required|integer',
          'sku_code' => 'required',
        "buyer_product_status" => "required|in:0,1",
        "seller_product_status" => "required|in:0,1",
        ]);
    
        if ($validator->fails()) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        $dataLama = Product::find($id);
        if (!$dataLama) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ["name"=>["produk tidak ada"]]
            ], 422);
        }
        $cekProduk = Product::where('name', $req->name)->first();
        if (($cekProduk)&&($dataLama->name!=$req->name)) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' =>  ["name"=>["produk sudah ada"]]
            ], 422);
        }
        $imagename=Str::random(5);
        $Product=Product::find($id);
        $Product->name=$req->name;
        $Product->cap_price=$req['cap-price'];
        $Product->product_category_id=$req->category_id;
        
        // SET DEFAULT HARGA JUAL KE 0 KARENA SUDAH OTOMATIS
        $Product->selling_price = 0; 
        
        $Product->discount=$req->discount??0;
        $Product->buyer_sku_code=$req->sku_code;
        $Product->buyer_product_status=$req->buyer_product_status;
        $Product->seller_product_status=$req->seller_product_status;
        if(($Product->buyer_product_status==0)||($Product->seller_product_status==0)){
          $Product->active='0';
        }
        if($req->image!=NULL){
            $mimetype = $req->image->getMimeType();
            $Product->image=$imagename.".".explode('/',$mimetype)[1];
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/product/'.$Product->image;

            if ($Product->image && file_exists($filePath)) {
                unlink($filePath);
            }
             $upload=$req->file('image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/product', $Product->image);
          }
        
        $simpan=$Product->save();

        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Produk berhasil diedit',
                'data' => $Product
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal mengedit produk, silakan coba lagi'
          ], 500);
        
    }

    public function switchActivate(Request $req,$game_id,$id){      
        $Product = Product::find($id);
        if (!$Product) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ["name"=>["produk tidak ada"]]
            ], 422);
        }
        if(($Product->buyer_product_status==0)||($Product->seller_product_status==0)){
          $Product->active='0';
          if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'untuk mengaktifkan produk, pastikan buyer_product_status dan seller_product_status juga aktif',
                'data' => $Product
            ], 200);
          }
          return response()->json([
            'status' => 'error',
            'message' => 'Gagal mengedit produk, silakan coba lagi'
        ], 500);
        }
        $Product->active=$Product->active=='0'?'1':'0';
        $simpan=$Product->save();

        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => $Product->active=='0'?'Produk berhasil dinonaktifkan':'Produk berhasil diaktifkan',
                'data' => $Product
            ], 200);
          }
        return response()->json([
            'status' => 'error',
            'message' => 'Gagal mengedit produk, silakan coba lagi'
        ], 500);
        
    }

    public function remove(Request $req,$id){
        $Product=Product::find($id);
        if (!$Product) {
          return response()->json([
              'status' => 'error',
              'message' => 'produk tidak ada'
          ], 404);
        }
        $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/product/'.$Product->image;

        if ($Product->image && file_exists($filePath)) {
            unlink($filePath);
        }
        $simpan=$Product->delete();
        if($simpan){
          return response()->json([
                'status' => 'success',
                'message' => 'Produk berhasil dihapus'
            ], 200);
        }
        
    }
}