<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use App\Models\Game;
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;

class ProductCategoryController extends Controller
{
    public function index($game_id){
        $data=ProductCategory::where('game_id', $game_id)->get();
        // return response()->json($data);
        return response()->json($data);
    }
    public function getOne($game_id,$id){
      $data=ProductCategory::find($id);
      if(!$data){
        return response()->json([
                'status' => 'error',
                'message' => 'Data Tidak Ada']
            , 404); // 409 Conflict
      }
      return response()->json([
          'status' => 'success',
          'data'=>$data]
      , 200); 
    }
    public function prosesAdd(Request $req,$game_id){
        $validator = Validator::make($req->all(),[
            'name' => 'required',
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
          $cekProduk = ProductCategory::where('name', $req->name)->where('game_id', $game_id)->first();
          if ($cekProduk) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ['name'=>['Kategori sudah ada']]
            ], 422);
          }
        $ProductCategory=new ProductCategory;
        $imagename=Str::random(5);
          $ProductCategory->game_id=$game_id;
          $ProductCategory->name=$req->name;
          if($req->category_image!=NULL){
            $mimetype = $req->category_image->getMimeType();
            // if (Storage::disk('public')->exists('game/'.$Product->image)) {
            //     Storage::disk('public')->delete('game/'.$Product->image);
            // }
            $ProductCategory->image=$imagename.".".explode('/',$mimetype)[1];
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/category/'.$ProductCategory->image;

            if ($ProductCategory->image && file_exists($filePath)) {
                unlink($filePath);
            }
            
            //$path = $path = $req->file('image')->storeAs(
              //    'game',
                //  $ProductCategory->image,
                  //'public'
              //);
             $upload=$req->file('category_image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/category', $ProductCategory->image);
            //  var_dump($upload);
          }
          $simpan=$ProductCategory->save();
          if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Kategori berhasil disimpan',
                'data' => $ProductCategory
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal menyimpan kategori'
          ], 500);
          
    }
    
    public function prosesEdit(Request $req,$game_id,$id){
      $validator = Validator::make($req->all(),[
          'name' => 'required',
        ]);
    
        if ($validator->fails()) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        $dataLama = ProductCategory::find($id);
        if (!$dataLama) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ["name"=>["produk tidak ada"]]
            ], 422);
        }
        $cekProduk = ProductCategory::where('name', $req->name)->first();
        if (($cekProduk)&&($dataLama->name!=$req->name)) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' =>  ["name"=>["produk sudah ada"]]
            ], 422);
        }
        $imagename=Str::random(5);
        $ProductCategory=ProductCategory::find($id);
        $ProductCategory->name=$req->name;
        if($req->category_image!=NULL){
            $mimetype = $req->category_image->getMimeType();
            // if (Storage::disk('public')->exists('game/'.$Product->image)) {
            //     Storage::disk('public')->delete('game/'.$Product->image);
            // }
            $ProductCategory->image=$imagename.".".explode('/',$mimetype)[1];
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/category/'.$ProductCategory->image;

            if ($ProductCategory->image && file_exists($filePath)) {
                unlink($filePath);
            }
            
            
            //$path = $path = $req->file('image')->storeAs(
              //    'game',
                //  $ProductCategory->image,
                  //'public'
              //);
             $upload=$req->file('category_image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/category', $ProductCategory->image);
            //  var_dump($upload);
          }
        $simpan=$ProductCategory->save();

        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Kategori berhasil diedit',
                'data' => $ProductCategory
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal mengedit kategori, silakan coba lagi'
          ], 500);
        
    }
    public function remove(Request $req,$game_id,$id){
        $ProductCategory=ProductCategory::find($id);
        if (!$ProductCategory) {
          return response()->json([
              'status' => 'error',
              'message' => 'kategori tidak ada'
          ], 404);
        }
        $simpan=$ProductCategory->delete();
        if($simpan){
          return response()->json([
                'status' => 'success',
                'message' => 'Produk berhasil dihapus'
            ], 200);
        }
        
    }
}
