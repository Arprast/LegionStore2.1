<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AppConfig;
use App\Models\SpecialOffers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class AppConfigController extends Controller
{
    public function index(){
        return view('admin.app-config.index');
    }

   public function data()
    {
        $data = AppConfig::first();
        $specialOffer = SpecialOffers::first();
        
        if($data){
            $data->fs_start = $data->flash_sale_start ? date('Y-m-d H:i', strtotime($data->flash_sale_start)) : '';
            $data->fs_end = $data->flash_sale_end ? date('Y-m-d H:i', strtotime($data->flash_sale_end)) : '';
            $data->auto_active = $data->auto_active_at ? date('Y-m-d H:i', strtotime($data->auto_active_at)) : '';
            $data->is_flash_sale_active = $data->is_flash_sale_active ?? 0;
            $data->biaya_admin = $data->biaya_admin ?? 0; // TAMBAHAN BARU
        }

        if($specialOffer != null && $specialOffer->image != null){
            $specialOffer->imagePath = asset('storage/special_offer/'.$specialOffer->image);
        }
        
        return response()->json(compact('data', 'specialOffer'), 200);
    }

    public function updateFlashSale(Request $req)
    {
        try {
            $appConfig = AppConfig::first();
            if(!$appConfig) $appConfig = new AppConfig();

            $appConfig->flash_sale_start = $req->flash_sale_start ? $req->flash_sale_start : null;
            $appConfig->flash_sale_end   = $req->flash_sale_end ? $req->flash_sale_end : null;
            $appConfig->is_flash_sale_active = $req->is_flash_sale_active; 
            $appConfig->save();

            $pesan = $req->is_flash_sale_active == 1 ? 'diaktifkan' : 'dinonaktifkan';

            return response()->json(['status' => 'success', 'message' => 'Jadwal Flash Sale berhasil ' . $pesan . '!'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error DB: ' . $e->getMessage()], 500);
        }
    }

    public function updateWebStatus(Request $req)
    {
        try {
            $configs = AppConfig::orderBy('id', 'asc')->get();
            if ($configs->count() > 1) {
                foreach ($configs->skip(1) as $duplicate) {
                    $duplicate->delete();
                }
            }

            $appConfig = AppConfig::first();
            if(!$appConfig) $appConfig = new AppConfig();

            $appConfig->website_status      = $req->website_status;
            $appConfig->auto_active_at      = $req->auto_active_at ? $req->auto_active_at : null;
            $appConfig->maintenance_message = $req->maintenance_message;
            $appConfig->save();

            return response()->json([
                'status' => 'success', 
                'message' => 'Status Website diperbarui menjadi: ' . strtoupper($req->website_status)
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error DB: ' . $e->getMessage()], 500);
        }
    }

    public function updatePromo(Request $req)
    {
        $validator = Validator::make($req->all(),[
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
            'status'  => 'required|in:0,1',
            'image'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
    
        if ($validator->fails()) {
          return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $specialOffer = SpecialOffers::first();
        if (!$specialOffer) $specialOffer = new SpecialOffers();

        $specialOffer->title     = $req->title;
        $specialOffer->content   = $req->content;
        $specialOffer->is_active = $req->status;

        if($req->hasFile('image')){
            $image = $req->file('image');
            $imageName = Str::random(10) . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('storage/special_offer');

            if(!File::isDirectory($destinationPath)){ File::makeDirectory($destinationPath, 0777, true, true); }
            if($specialOffer->image && File::exists($destinationPath . '/' . $specialOffer->image)){ File::delete($destinationPath . '/' . $specialOffer->image); }

            $image->move($destinationPath, $imageName);
            $specialOffer->image = $imageName;
        }

        $specialOffer->save();
        return response()->json(['status' => 'success', 'message' => 'Popup Promo berhasil diperbarui!'], 200);
    }

    // ==========================================
    // TAMBAHAN BARU: Fungsi Simpan Biaya Admin
    // ==========================================
   // ==========================================
    // TAMBAHAN BARU: Fungsi Simpan Biaya Admin (PERSEN)
    // ==========================================
    public function updateBiayaAdmin(Request $req)
    {
        try {
            $appConfig = AppConfig::first();
            if(!$appConfig) $appConfig = new AppConfig();

            // Ubah koma menjadi titik (jika user iseng ngetik pakai koma)
            $nominalPersen = str_replace(',', '.', $req->biaya_admin);
            
            // Simpan sebagai angka desimal murni
            $appConfig->biaya_admin = (float) $nominalPersen;
            $appConfig->save();

            return response()->json(['status' => 'success', 'message' => 'Biaya Admin (' . $appConfig->biaya_admin . '%) berhasil diperbarui!'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Error DB: ' . $e->getMessage()], 500);
        }
    }
}