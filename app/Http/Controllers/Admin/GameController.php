<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Game;
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;

class GameController extends Controller
{
    public function index(){
        return view('admin.game.index');
    }
    
    public function data()
    {
        return DataTables::of(Game::query())
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
              $html='
                  <div class="btn-group">
                    <button href="#" data-id="'.$row->id.'" class="btn btn-xs btn-warning edit-btn">Edit</button>
                    <button href="#" data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn">Hapus</button>
                  </div>
                  <div class="btn-group" style="margin-top:5px;">
                    <a href="/admin/game/product/'.$row->id.'" class="btn btn-xs btn-primary">Produk</a>
                  </div>
                  <div class="btn-group" style="margin-top:5px;">
                    <a href="/admin/game/flash-sale/'.$row->id.'" class="btn btn-xs btn-success">Flash Sale</a>
                  </div>
                  <div class="btn-group" style="margin-top:5px;">
                    <a href="/admin/game/faq/'.$row->id.'" class="btn btn-xs btn-success">FAQ</a>
                  </div>
                ';
                if($row->is_trending=='1'){
                  $html.='
                  <div class="btn-group" style="margin-top:5px;">
                    <button href="#" data-id="'.$row->id.'" class="btn btn-xs btn-dark toggle-trending-btn">Non-Trending</button>
                  </div>
                  ';
                }else{
                  $html.='
                  <div class="btn-group" style="margin-top:5px;">
                    <button href="#" data-id="'.$row->id.'" class="btn btn-xs btn-dark toggle-trending-btn">Trending</button>
                  </div>
                  ';
                }
                return $html;
            })
            ->rawColumns(['action'])
            ->make(true);
    }
    
    public function getOne($id){
      $data=Game::find($id);
      if(!$data){
        return response()->json([
                'status' => 'error',
                'message' => 'Data Tidak Ada']
            , 404); 
      }
      $data->imagePath=asset('storage/game/'.$data->image);
      $data->letterLogoPath=asset('storage/letter_logo/'.$data->letter_logo);
      $data->bannerPath=asset('storage/banner/'.$data->banner);
      return response()->json([
                'status' => 'success',
                'data'=>$data]
            , 200); 
    }
    
    public function prosesAdd(Request $req){
        $validator = Validator::make($req->all(),[
            'name' => 'required',
            'publisher' => 'required',
            'category' => 'required',
            'tipe_profit' => 'required|in:persen,nominal',
            'profit_guest' => 'required|numeric',
            'profit_customer' => 'required|numeric',
            'profit_membership' => 'required|numeric',
            'profit_mitra' => 'required|numeric',
          ]);
      
          if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
          }
          
          $cekGame = Game::where('name', $req->name)->first();
          if ($cekGame) {
            return response()->json([
                'status' => 'error',
                'message' => 'Game sudah ada',
                'errors' => ['name' => ['Game sudah ada']]
            ], 409); 
          }
          
          $Game=new Game;
          $Game->name=$req->name;
          $Game->slug=Str::slug($req->name); // Otomatis buat slug
          $Game->publisher=$req->publisher;
          $Game->category=$req->category;
          
          $Game->tipe_profit=$req->tipe_profit;
          $Game->profit_guest=$req->profit_guest;
          $Game->profit_customer=$req->profit_customer;
          $Game->profit_membership=$req->profit_membership;
          $Game->profit_mitra=$req->profit_mitra;
          
          $Game->account_id_method = '-';
          $Game->server_input_method = Null;
          $Game->regions = null;
          
          $Game->provider_transaksi = $req->provider_transaksi ?? 'digiflaz';
          $Game->is_cek_id_aktif = $req->is_cek_id_aktif ?? 0;
          $Game->provider_cek_id = $req->provider_cek_id;
          $Game->kode_cek_id = $req->kode_cek_id;
          $Game->is_trending='0';

          // SET NILAI DEFAULT AGAR TIDAK ERROR SQL 1364
          $Game->image = 'default.jpg'; 
          $Game->letter_logo = 'default.jpg';
          $Game->banner = 'default.jpg';
          
          // FOTO (Hanya diproses jika ada file)
          if($req->hasFile('image')){
            $mimetype = $req->image->getMimeType();
            $Game->image=Str::random(5).".".explode('/',$mimetype)[1];
            $req->file('image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/game', $Game->image);
          }
          if($req->hasFile('letter_logo')){
            $mimetype = $req->letter_logo->getMimeType();
            $Game->letter_logo=Str::random(5).".".explode('/',$mimetype)[1];
            $req->file('letter_logo')->move($_SERVER['DOCUMENT_ROOT'].'/storage/letter_logo', $Game->letter_logo);
          }
          if($req->hasFile('banner_image')){
            $mimetype = $req->banner_image->getMimeType();
            $Game->banner=Str::random(5).".".explode('/',$mimetype)[1];
            $req->file('banner_image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/banner', $Game->banner);
          }
          
          $simpan=$Game->save();
          if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Game berhasil disimpan',
                'data' => $Game
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal menyimpan game, silakan coba lagi'
          ], 500);
          
    }
    
    public function prosesEdit(Request $req,$id){
      $validator = Validator::make($req->all(),[
          'name' => 'required',
          'publisher' => 'required',
          'category' => 'required',
          'tipe_profit' => 'required|in:persen,nominal',
          'profit_guest' => 'required|numeric',
          'profit_customer' => 'required|numeric',
          'profit_membership' => 'required|numeric',
          'profit_mitra' => 'required|numeric',
        ]);
    
        if ($validator->fails()) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $Game = Game::find($id);
        if (!$Game) {
          return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan'
            ], 404);
        }
        
        $cekGame = Game::where('name', $req->name)->where('id', '!=', $id)->first();
        if ($cekGame) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' =>  ["name"=>["game sudah ada"]]
            ], 422);
        }
        
        $Game->name=$req->name;
        $Game->slug=Str::slug($req->name);
        $Game->publisher=$req->publisher;
        $Game->category=$req->category;

        $Game->tipe_profit=$req->tipe_profit;
        $Game->profit_guest=$req->profit_guest;
        $Game->profit_customer=$req->profit_customer;
        $Game->profit_membership=$req->profit_membership;
        $Game->profit_mitra=$req->profit_mitra;

        $Game->provider_transaksi = $req->provider_transaksi ?? 'digiflaz';
        $Game->is_cek_id_aktif = $req->is_cek_id_aktif ?? 0;
        $Game->provider_cek_id = $req->provider_cek_id;
        $Game->kode_cek_id = $req->kode_cek_id;
        
        // FOTO (Hapus file lama jika upload file baru)
        if($req->hasFile('image')){
            $mimetype = $req->image->getMimeType();
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/game/'.$Game->image;
            if ($Game->image && file_exists($filePath) && $Game->image != 'default.jpg') {
                unlink($filePath);
            }
            $Game->image=Str::random(5).".".explode('/',$mimetype)[1];
            $req->file('image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/game', $Game->image);
        }
        if($req->hasFile('letter_logo')){
            $mimetype = $req->letter_logo->getMimeType();
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/letter_logo/'.$Game->letter_logo;
            if ($Game->letter_logo && file_exists($filePath) && $Game->letter_logo != 'default.jpg') {
                unlink($filePath);
            }
            $Game->letter_logo=Str::random(5).".".explode('/',$mimetype)[1];
            $req->file('letter_logo')->move($_SERVER['DOCUMENT_ROOT'].'/storage/letter_logo', $Game->letter_logo);
        }
        if($req->hasFile('banner_image')){
            $mimetype = $req->banner_image->getMimeType();
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/banner/'.$Game->banner;
            if ($Game->banner && file_exists($filePath) && $Game->banner != 'default.jpg') {
                unlink($filePath);
            }
            $Game->banner=Str::random(5).".".explode('/',$mimetype)[1];
            $req->file('banner_image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/banner', $Game->banner);
        }
          
        $simpan=$Game->save();

        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Game berhasil diedit',
                'data' => $Game
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal mengedit game, silakan coba lagi'
          ], 500);
    }
    
    public function toggleTrending(Request $req,$id){      
        $Game=Game::find($id);
        if (!$Game) {
          return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        }
        $Game->is_trending=$Game->is_trending=='1'?'0':'1';
        $simpan=$Game->save();

        if($simpan){
          return response()->json([
              'status' => 'success',
              'message' => 'Trending berhasil diubah',
              'data' => $Game
          ], 200);
        }
        return response()->json([
            'status' => 'error',
            'message' => 'Gagal mengedit trending'
        ], 500);
    }
    
    public function remove(Request $req,$id){
        $Game=Game::find($id);
        if (!$Game) {
          return response()->json(['status' => 'error', 'message' => 'Game tidak ditemukan'], 404);
        }
        
        $files = [
            '/storage/game/' . $Game->image,
            '/storage/letter_logo/' . $Game->letter_logo,
            '/storage/banner/' . $Game->banner
        ];

        foreach ($files as $file) {
            $path = $_SERVER['DOCUMENT_ROOT'] . $file;
            if (file_exists($path) && !str_contains($file, 'default.jpg')) {
                unlink($path);
            }
        }
        
        if($Game->delete()){
          return response()->json(['status' => 'success', 'message' => 'Game berhasil dihapus'], 200);
        }
    }

    public function productIndex($id) {
        $game = Game::findOrFail($id);
        $game->imagePath = $game->image ? asset('storage/game/'.$game->image) : asset('storage/game/default.jpg');
        $game->letterLogoPath = $game->letter_logo ? asset('storage/letter_logo/'.$game->letter_logo) : asset('storage/letter_logo/default.jpg');
        $game->bannerPath = $game->banner ? asset('storage/banner/'.$game->banner) : asset('storage/banner/default.jpg');

        return view('admin.game.product', compact('game'));
    }

public function updateVisualServer(Request $req, $id) {
        $game = Game::find($id);
        if (!$game) {
            return response()->json(['status' => 'error', 'message' => 'Game tidak ditemukan'], 404);
        }

        $game->account_id_method = $req->account_id_method ?? '-';
        
        // PERBAIKAN DI BARIS INI (Menggunakan null, bukan string kosong)
        $game->server_input_method = $req->server_input_method ?? null; 
        
        $game->server_id_method = $req->server_id_method ?? 'Zone ID / Server'; 

        if ($game->server_input_method == "auto") {
            $game->regions = $req->regions;
        } else {
            $game->regions = null;
        }

        if ($req->hasFile('image')) {
            $mimetype = $req->image->getMimeType();
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/game/'.$game->image;
            if ($game->image && file_exists($filePath) && $game->image != 'default.jpg') unlink($filePath);
            $game->image = Str::random(5).".".explode('/', $mimetype)[1];
            $req->file('image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/game', $game->image);
        }

        if ($req->hasFile('letter_logo')) {
            $mimetype = $req->letter_logo->getMimeType();
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/letter_logo/'.$game->letter_logo;
            if ($game->letter_logo && file_exists($filePath) && $game->letter_logo != 'default.jpg') unlink($filePath);
            $game->letter_logo = Str::random(5).".".explode('/', $mimetype)[1];
            $req->file('letter_logo')->move($_SERVER['DOCUMENT_ROOT'].'/storage/letter_logo', $game->letter_logo);
        }

        if ($req->hasFile('banner_image')) {
            $mimetype = $req->banner_image->getMimeType();
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/banner/'.$game->banner;
            if ($game->banner && file_exists($filePath) && $game->banner != 'default.jpg') unlink($filePath);
            $game->banner = Str::random(5).".".explode('/', $mimetype)[1];
            $req->file('banner_image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/banner', $game->banner);
        }

        if ($game->save()) {
            return response()->json(['status' => 'success', 'message' => 'Visual dan Server berhasil diperbarui']);
        }
        return response()->json(['status' => 'error', 'message' => 'Gagal memperbarui data'], 500);
    }
}