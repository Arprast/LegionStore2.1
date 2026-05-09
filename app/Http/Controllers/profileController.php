<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
class profileController extends Controller
{
    public function index(){
        // $data=User::all();
        return view('profile');
    }
    public function prosesEdit(Request $req){
      $validator = Validator::make($req->all(),[
          'name'     => 'required|string|max:255',
          'email'    => 'required|email|max:255',
          'phone_number' => 'required|string|max:20',
          
        ]);
    
        if ($validator->fails()) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        $dataLama = User::find(auth()->user()->id);
        $cekUser = User::where('name', $req->name)->first();
        if (($cekUser)&&($dataLama->name!=$req->name)) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' =>  ["name"=>["Nama User sudah ada"]]
            ], 422);
        }
        $cekEmail = User::where('email', $req->email)->first();
        if (($cekEmail)&&($dataLama->email!=$req->email)) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' =>  ["email"=>["Email sudah ada"]]
            ], 422);
        }
        $imagename=Str::random(5);
        $User=User::find(auth()->user()->id);
        $User->name=$req->name;
        $User->email=$req->email;
        $User->phone_number=$req->phone_number;
        $simpan=$User->save();
        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Profil Berhasil Diedit',
                'data' => $User
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal mengedit user, silakan coba lagi'
          ], 500);
        
        
    }
    public function updatePin(Request $request)
    {
        $request->validate([
            'payment_pin' => 'required|digits:6|numeric',
            'payment_pin_confirmation' => 'required|same:payment_pin',
        ], [
            'payment_pin.required' => 'PIN wajib diisi.',
            'payment_pin.digits' => 'PIN harus persis 6 angka.',
            'payment_pin.numeric' => 'PIN hanya boleh berisi angka.',
            'payment_pin_confirmation.same' => 'Konfirmasi PIN tidak cocok.',
        ]);

        $user = auth()->user();
        $user->payment_pin = \Illuminate\Support\Facades\Hash::make($request->payment_pin);
        $user->save();

        return back()->with('success', 'PIN Pembayaran berhasil diatur!');
    }
    // ==========================================
    // FUNGSI UNTUK FITUR PIN WALLET LG-COIN
    // ==========================================

    // 1. Fungsi Buat PIN Pertama Kali
    public function createPin(Request $request)
    {
        $request->validate([
            'payment_pin' => 'required|digits:6|numeric',
            'payment_pin_confirmation' => 'required|same:payment_pin',
        ]);

        $user = auth()->user();
        $user->payment_pin = \Illuminate\Support\Facades\Hash::make($request->payment_pin);
        $user->save();

        return response()->json(['status' => 'success', 'message' => 'PIN berhasil dibuat']);
    }

    // 2. Fungsi Kirim OTP ke Email
    public function sendPinOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        if($request->email != auth()->user()->email) {
            return response()->json(['message' => 'Gagal! Email tidak sesuai dengan akun Anda.'], 400);
        }

        // Generate 6 digit OTP acak dan simpan di session Laravel
        $otp = rand(100000, 999999);
        session(['pin_otp' => $otp]);

        // =======================================================
        // TARUH KODE KIRIM EMAIL ANDA SEBELUMNYA DI SINI 
        // Contoh: Mail::to($request->email)->send(new SendOtpMail($otp));
        // =======================================================

        return response()->json(['status' => 'success', 'message' => 'OTP terkirim', 'otp_debug' => $otp]); 
        // (Hapus 'otp_debug' jika sudah live/production agar OTP tidak bocor di console)
    }

    // 3. Fungsi Ubah PIN dengan Validasi OTP
    public function changePin(Request $request)
    {
        $request->validate([
            'otp_code' => 'required',
            'new_pin' => 'required|digits:6|numeric',
            'new_pin_confirmation' => 'required|same:new_pin',
        ]);

        // Cek kecocokan OTP di session
        if($request->otp_code != session('pin_otp')) {
            return response()->json(['message' => 'Kode OTP salah atau sudah kadaluarsa!'], 400);
        }

        // Jika OTP Benar, Ganti PIN-nya
        $user = auth()->user();
        $user->payment_pin = \Illuminate\Support\Facades\Hash::make($request->new_pin);
        $user->save();

        // Hapus session OTP agar tidak bisa dipakai 2x
        session()->forget('pin_otp');

        return response()->json(['status' => 'success', 'message' => 'PIN berhasil diperbarui']);
    }

    public function upgradeMembership(Request $request) {
        $user = auth()->user();
        if ($user->role !== 'customer') return response()->json(['message' => 'Hanya pengguna berstatus Customer yang bisa melakukan Upgrade.'], 403);

        $settings = \Illuminate\Support\Facades\Storage::disk('local')->exists('membership_settings.json') 
                    ? json_decode(\Illuminate\Support\Facades\Storage::disk('local')->get('membership_settings.json'), true) 
                    : ['price' => 50000, 'target_topup' => 500000];
        
        $method = $request->input('method', 'buy'); 

        // 1. JALUR MISI TOP UP
        if ($method === 'mission') {
            $totalTopupSah = \App\Models\Topup::where('user_id', $user->id)->where('status', 'success')->sum('amount');
            if ($totalTopupSah < $settings['target_topup']) return response()->json(['message' => 'Total Akumulasi Top Up Anda belum memenuhi syarat misi!'], 400);

            $user->role = 'membership';
            $user->save();
            return response()->json(['message' => 'Misi Selesai! Akun Anda berhasil naik ke level Membership secara Gratis.']);
        } 
        // 2. JALUR BELI INSTAN
        else {
            $price = $settings['price'];
            $wallet = \App\Models\Wallet::where('user_id', $user->id)->first();
            
            if (!$wallet || $wallet->balance < $price) return response()->json(['message' => 'Saldo LG-Coin tidak mencukupi untuk membeli Upgrade Instan.'], 400);

            $wallet->balance -= $price;
            $wallet->save();
            $wallet->mutations()->create([
                'reference_id' => 'UPG-' . time(), 'description' => 'Biaya Beli Upgrade Role', 'type' => 'debit', 'amount' => $price
            ]);

            $user->role = 'membership';
            $user->save();
            return response()->json(['message' => 'Pembelian Berhasil! Akun Anda kini berstatus Membership.']);
        }
    }
}
