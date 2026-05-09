<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Faq;
use App\Models\Game;
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;

class FaqController extends Controller
{
    public function index($game_id){
        $data=Game::where('id', $game_id)->first();
        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Game tidak ditemukan'
            ], 404);
        }
        return view('admin.faq.index', compact('data','game_id'));
    }
    public function data($game_id)
    {
        return DataTables::of(Faq::where('game_id', $game_id))
            ->addIndexColumn()
            ->addColumn('action', function ($row) use($game_id) {
                $actionButton='<div class="btn-group">';
                $actionButton.='                  
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-warning edit-btn">Edit</a>
                ';
                
                $actionButton.='<a data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn">Hapus</a>
                  </div>';
                return $actionButton;
            })
            ->rawColumns(['action'])
            ->make(true);
    }
    public function getOne($game_id,$id){
      $data=Faq::find($id);
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
            'title' => 'required',
            'content' => 'required',
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
                'errors' => ['title'=>['Game tidak ada']]
            ], 404);
          }
          $cekFaq = Faq::where('title', $req->title)->where('game_id', $game_id)->first();
          if ($cekFaq) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ['title'=>['FAQ sudah ada']]
            ], 422);
          }
        $faq=new Faq;
          $faq->game_id=$game_id;
          $faq->title=$req->title;
          $faq->content=$req->content;
          $simpan=$faq->save();
          if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'FAQ berhasil disimpan',
                'data' => $faq
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal menyimpan FAQ'
          ], 500);
          
    }
    
    public function prosesEdit(Request $req,$game_id,$id){
      $validator = Validator::make($req->all(),[
          'title' => 'required',
          'content' => 'required', 
        ]);
    
        if ($validator->fails()) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        $dataLama = Faq::find($id);
        if (!$dataLama) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ["title"=>["FAQ tidak ada"]]
            ], 422);
        }
        $cekFaq = Faq::where('title', $req->title)->where('game_id', $game_id)->first();
        if (($cekFaq)&&($dataLama->title!=$req->title)) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' =>  ["title"=>["FAQ sudah ada"]]
            ], 422);
        }
        $faq=Faq::find($id);
        $faq->title=$req->title;
        $faq->content=$req->content;
        $simpan=$faq->save();

        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'FAQ berhasil diedit',
                'data' => $faq
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal mengedit FAQ, silakan coba lagi'
          ], 500);
        
    }
    public function remove(Request $req,$game_id,$id){
        $faq=Faq::find($id);
        if (!$faq) {
          return response()->json([
              'status' => 'error',
              'message' => 'FAQ tidak ada'
          ], 404);
        }
        $simpan=$faq->delete();
        if($simpan){
          return response()->json([
                'status' => 'success',
                'message' => 'FAQ berhasil dihapus'
            ], 200);
        }
        
    }
}
