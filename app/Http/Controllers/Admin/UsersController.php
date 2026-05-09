<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Validator;
use Str;
use App\Models\ActivityLog;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class UsersController extends Controller
{
    public function index(){
        return view('admin.user.index');
    }

    public function data(Request $request)
    {
        $query = User::query();

        // Fitur Filter Tab: Membaca role yang diminta oleh Datatables
        if ($request->has('role') && $request->role != '') {
            $query->where('role', $request->role);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('created_at', function ($row) {
                  return Carbon::parse($row->created_at)
                      ->timezone('Asia/Jakarta')
                      ->format('d-m-Y H:i');
              })
            ->addColumn('action', function ($row) {
                // Merapikan tombol Aksi agar berjajar rapi dan menambahkan tombol Lihat Data
                return '
                  <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                    <button data-id="'.$row->id.'" class="btn btn-xs btn-primary view-detail-btn" style="background-color: #007bff; border:none; color:white;"><i class="fa fa-eye"></i> Detail</button>
                    <button data-id="'.$row->id.'" class="btn btn-xs btn-info view-log-btn" style="background-color: #17a2b8; border:none; color:white;"><i class="fa fa-history"></i> Log</button>
                    <button data-id="'.$row->id.'" class="btn btn-xs btn-warning edit-data-btn"><i class="fa fa-edit"></i> Edit</button>
                    <button data-id="'.$row->id.'" class="btn btn-xs btn-warning edit-password-btn"><i class="fa fa-key"></i> Pass</button>
                    <button data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn"><i class="fa fa-trash"></i> Hapus</button>
                  </div>
                ';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    // ==========================================
    // FUNGSI BARU: MENGAMBIL DETAIL USER & RIWAYAT
    // ==========================================
    public function getUserDetail($id) {
        // Relasi wallet.mutations untuk mengambil riwayat mutasi
        $user = User::with([
            'product_purchases.products', 
            'topups', 
            'wallet',
            'wallet.mutations' => function ($query) {
                $query->orderBy('created_at', 'desc'); // Urutkan mutasi dari yang terbaru
            }
        ])->find($id);
        
        if(!$user){
            return response()->json(['status' => 'error', 'message' => 'Data Tidak Ada'], 404);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $user
        ], 200);
    }

    public function getOne($id){
      $data=User::find($id);
      if(!$data){
        return response()->json([
                'status' => 'error',
                'message' => 'Data Tidak Ada'
            ], 404); 
      }
      return response()->json([
                'status' => 'success',
                'data'=>$data
            ], 200); 
    }

    public function prosesAdd(Request $req){
        // 1. Validasi Input (UPDATE ROLE)
        $validator = Validator::make($req->all(),[
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'role'     => 'required|in:admin,supervisor,mitra,membership,customer', 
            'password' => 'required|string|min:8',
            'confirm'  => 'required|string|same:password',
        ]);
      
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Penjaga Batas Supervisor (Maksimal 3)
        if ($req->role === 'supervisor') {
            $countSupervisor = User::where('role', 'supervisor')->count();
            if ($countSupervisor >= 3) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi gagal',
                    'errors' => ['role' => ['Maksimal hanya boleh ada 3 akun Supervisor di sistem ini!']]
                ], 422);
            }
        }

        // 3. Cek Duplikat Nama & Email
        $cekUser = User::where('name', $req->name)->first();
        if ($cekUser) {
            return response()->json(['status' => 'error', 'message' => 'Nama User sudah ada', 'errors' => ['name' => ['Nama User sudah ada']]], 409);
        }

        $cekEmail = User::where('email', $req->email)->first();
        if ($cekEmail) {
            return response()->json(['status' => 'error', 'message' => 'Email sudah ada', 'errors' => ['email' => ['Email sudah ada']]], 409);
        }

        // 4. Proses Simpan User Baru
        $User = new User;
        $User->name = $req->name;
        $User->email = $req->email;
        $User->role = $req->role;
        $User->password = \Illuminate\Support\Facades\Hash::make($req->password);
        $simpan = $User->save();

        // 5. Perekaman CCTV (Activity Log)
        if($simpan){
            \App\Models\ActivityLog::create([
                'user_id' => auth()->user()->id,
                'action' => 'Tambah User',
                'description' => auth()->user()->name . ' menambahkan user baru bernama: ' . $User->name
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Users berhasil disimpan',
                'data' => $User
            ], 200);
        }
        
        return response()->json([
            'status' => 'error',
            'message' => 'Gagal menyimpan user, silakan coba lagi'
        ], 500);
    }

    public function prosesEditData(Request $req, $id){
        // 1. Validasi Input (UPDATE ROLE)
        $validator = Validator::make($req->all(),[
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'role'     => 'required|in:admin,supervisor,mitra,membership,customer',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $dataLama = User::find($id);
        if (!$dataLama) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan', 'errors' => ["name"=>["data tidak ada"]]], 404);
        }

        // 2. Penjaga Turun Jabatan
        if ($dataLama->role === 'supervisor' && $req->role !== 'supervisor') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses Ditolak',
                'errors' => ['role' => ['Akun Supervisor tidak boleh diturunkan jabatannya!']]
            ], 422);
        }

        // 3. Penjaga Naik Jabatan (Limit 3)
        if ($dataLama->role !== 'supervisor' && $req->role === 'supervisor') {
            $countSupervisor = User::where('role', 'supervisor')->count();
            if ($countSupervisor >= 3) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi gagal',
                    'errors' => ['role' => ['Maksimal hanya boleh ada 3 akun Supervisor di sistem ini!']]
                ], 422);
            }
        }

        // 4. Cek Duplikat
        $cekUser = User::where('name', $req->name)->first();
        if (($cekUser) && ($dataLama->name != $req->name)) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'errors' =>  ["name"=>["Nama User sudah ada"]]], 422);
        }

        $cekEmail = User::where('email', $req->email)->first();
        if (($cekEmail) && ($dataLama->email != $req->email)) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'errors' =>  ["email"=>["Email sudah ada"]]], 422);
        }

        // 5. Proses Update & Rekam CCTV
        $dataLama->name = $req->name;
        $dataLama->email = $req->email;
        $dataLama->role = $req->role;
        $simpan = $dataLama->save();

        if($simpan){
            \App\Models\ActivityLog::create([
                'user_id' => auth()->user()->id,
                'action' => 'Edit User',
                'description' => '[' . strtoupper(auth()->user()->role) . '] ' . auth()->user()->name . ' mengedit akun bernama: ' . $dataLama->name . ' (sebagai ' . $dataLama->role . ')'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Users berhasil diedit',
                'data' => $dataLama
            ], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Gagal mengedit user, silakan coba lagi'], 500);
    }

    public function prosesEdiPassword(Request $req, $id){
        $validator = Validator::make($req->all(),[
            'password' => 'required|string|min:8',
            'confirm'  => 'required|string|same:password',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $User = User::find($id);
        if (!$User) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data tidak ditemukan',
                'errors' => ["password"=>["data tidak ada"]]
            ], 404);
        }
        
        $User->password = Hash::make($req->password);
        $simpan = $User->save(); 

        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil diedit',
                'data' => $User
            ], 200);
        }
        return response()->json([
            'status' => 'error',
            'message' => 'Gagal mengedit password, silakan coba lagi'
        ], 500);
    }

    public function remove(Request $req, $id){
        $User = User::find($id);
        
        if (!$User) {
            return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        }

        // 1. Penjaga Kebal Hapus
        if ($User->role === 'supervisor') {
            return response()->json([
                'status' => 'error',
                'message' => 'Akun dengan role Supervisor tidak boleh dihapus!'
            ], 403); 
        }

        // 2. Proses Hapus & Rekam CCTV
        $namaYangDihapus = $User->name; 
        $simpan = $User->delete();
        
        if($simpan){
            \App\Models\ActivityLog::create([
                'user_id' => auth()->user()->id,
                'action' => 'Hapus User',
                'description' => '[' . strtoupper(auth()->user()->role) . '] ' . auth()->user()->name . ' telah MENGHAPUS user bernama: ' . $namaYangDihapus
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Users berhasil dihapus'
            ], 200);
        }
        return response()->json(['status' => 'error', 'message' => 'Gagal menghapus data'], 500);
    }

    public function getUserLogs($id){
        $logs = \App\Models\ActivityLog::where('user_id', $id)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function($log) {
                        $log->waktu = Carbon::parse($log->created_at)->timezone('Asia/Jakarta')->format('d-m-Y H:i:s');
                        return $log;
                    });
                    
        return response()->json([
            'status' => 'success',
            'data' => $logs
        ], 200);
    }

    // ==========================================
    // FUNGSI BARU: SET HARGA UPGRADE MEMBERSHIP
    // ==========================================

    public function setMembershipPrice(Request $request) {
        $request->validate([
            'price' => 'required|numeric|min:0',
            'target_topup' => 'required|numeric|min:0'
        ]);
        
        $settings = [
            'price' => (int) $request->price,
            'target_topup' => (int) $request->target_topup
        ];
        
        \Illuminate\Support\Facades\Storage::disk('local')->put('membership_settings.json', json_encode($settings));
        
        \App\Models\ActivityLog::create([
            'user_id' => auth()->user()->id,
            'action' => 'Pengaturan Upgrade',
            'description' => auth()->user()->name . ' merubah Harga Upgrade: Rp ' . number_format($request->price, 0, ',', '.') . ' & Target Misi: Rp ' . number_format($request->target_topup, 0, ',', '.')
        ]);

        return response()->json(['status'=>'success','message'=>'Pengaturan Syarat Upgrade Membership berhasil disimpan!']);
    }
}