<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Carousel;
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;
class CarouselController extends Controller
{
    public function index(){
        // $data=Carousel::all();
        return view('admin.carousel.index');
    }
    public function data()
    {
        return DataTables::of(Carousel::query())
            ->addIndexColumn()
            ->addColumn('action', function ($row) {
                return '
                  <button data-id="'.$row->id.'" class="btn btn-xs btn-warning edit-btn">Edit</button>
                  <button data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn">Hapus</button>
                ';
            })
            ->rawColumns(['action'])
            ->make(true);
    }
    public function getOne($id){
      $data=Carousel::find($id);
      if(!$data){
        return response()->json([
                'status' => 'error',
                'message' => 'Data Tidak Ada']
            , 404); // 409 Conflict
      }
      $data->imagePath=asset('storage/carousel/'.$data->image);
      return response()->json([
                'status' => 'success',
                'data'=>$data]
            , 200); 
    }
    public function prosesAdd(Request $req){
        $validator = Validator::make($req->all(),[
            'title' => 'required',
            'link' => 'required',
            'image'=>'sometimes|mimetypes:image/jpeg,image/jpg,image/png|max:2000',
          ]);
      
          if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
          }
          $cekCarousel = Carousel::where('title', $req->title)->first();
          if ($cekCarousel) {
            return response()->json([
                'status' => 'error',
                'message' => 'Game sudah ada',
                'errors' => ['name' => ['Carousel sudah ada']]
            ], 409); // 409 Conflict
          }
          $imagename=Str::random(5);
          $Carousel=new Carousel;
          $Carousel->title=$req->title;
          $Carousel->link=$req->link;
          if($req->image!=NULL){
            $mimetype = $req->image->getMimeType();
            $Carousel->image=$imagename.".".explode('/',$mimetype)[1];
            // $path = $path = $req->file('image')->storeAs(
            //     'carousel',
            //     $Carousel->image,
            //     'public'
            // );
            $upload=$req->file('image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/carousel', $Carousel->image);
          }
          $simpan=$Carousel->save();
          if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Carousel berhasil disimpan',
                'data' => $Carousel
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal menyimpan carousel, silakan coba lagi'
          ], 500);
          
    }
    public function prosesEdit(Request $req,$id){
      $validator = Validator::make($req->all(),[
          'title' => 'required',
          'link' => 'required',
          'image'=>'sometimes|mimetypes:image/jpeg,image/jpg,image/png|max:2000',
        ]);
    
        if ($validator->fails()) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        $dataLama = Carousel::find($id);
        if (!$dataLama) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ["name"=>["data tidak ada"]]
            ], 422);
        }
        $cekCarousel = Carousel::where('title', $req->title)->first();
        if (($cekCarousel)&&($dataLama->title!=$req->title)) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' =>  ["name"=>["carousel sudah ada"]]
            ], 422);
        }
        $imagename=Str::random(5);
        $Carousel=Carousel::find($id);
        $Carousel->title=$req->title;
          $Carousel->link=$req->link;
        if($req->image!=NULL){
            $mimetype = $req->image->getMimeType();
            // if (Storage::disk('public')->exists('game/'.$Game->image)) {
            //     Storage::disk('public')->delete('game/'.$Game->image);
            // }
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/carousel/'.$Carousel->image;

            if ($Carousel->image && file_exists($filePath)) {
                unlink($filePath);
            }
            $Carousel->image=$imagename.".".explode('/',$mimetype)[1];
            
            //$path = $path = $req->file('image')->storeAs(
              //    'game',
                //  $Game->image,
                  //'public'
              //);
             $upload=$req->file('image')->move($_SERVER['DOCUMENT_ROOT'].'/storage/carousel', $Carousel->image);
            //  var_dump($upload);
          }
          if($req->letter_logo!=NULL){
            $mimetype = $req->letter_logo->getMimeType();
            $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/letter_logo/'.$Carousel->letter_logo;

            if ($Carousel->letter_logo && file_exists($filePath)) {
                unlink($filePath);
            }
            $Carousel->letter_logo=Str::random(5).".".explode('/',$mimetype)[1];
            $upload=$req->file('letter_logo')->move($_SERVER['DOCUMENT_ROOT'].'/storage/letter_logo', $Carousel->letter_logo);
          }
        

          $simpan=$Carousel->save();

        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Carousel berhasil diedit',
                'data' => $Carousel
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal mengedit carousel, silakan coba lagi'
          ], 500);
        
    }
    public function remove(Request $req,$id){
        $Carousel=Carousel::find($id);
        if (!$Carousel) {
          return response()->json([
              'status' => 'error',
              'message' => 'game tidak ada'
          ], 404);
        }
        $filePath = $_SERVER['DOCUMENT_ROOT'].'/storage/carousel/'.$Carousel->image;

        if ($Carousel->image && file_exists($filePath)) {
            unlink($filePath);
        }
        $simpan=$Carousel->delete();
        if($simpan){
          return response()->json([
                'status' => 'success',
                'message' => 'Carousel berhasil dihapus'
            ], 200);
        }
        
    }
}
