<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailServices;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\NumberCode;
use Validator;

class RegisterController extends Controller
{
    public function showRegisterForm()
    {
        $numberCode = NumberCode::all();
        return view('auth.register', compact('numberCode'));
    }

    public function register(Request $request)
    {
        // Validasi: Nomor handphone sudah dihapus dari aturan ini
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|min:6|confirmed',
            'agree'     => 'accepted'
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        
        $user = new User;
        $user->name = $request->name;
        $user->email = $request->email;
        $user->otp = null; 
        $user->is_verified = "1"; // Set Langsung 1
        $user->role = "customer";
        
        // PERUBAHAN: Otomatis kosongkan nomor WA karena form tidak mengirimnya lagi
        $user->phone_number = null; 
        
        $user->password = Hash::make($request->password); // WAJIB DI HASH
        $simpan = $user->save();

        if(!$simpan) return "Gagal simpan user";

        // Langsung Login
        Auth::login($user);

        // Langsung lempar ke beranda utama
        return redirect('/')->with('success', 'Selamat datang!');
    }

    public function continueRegistrationProcess(Request $request)
    {
        $otp = $request->input('otp');
        $user = User::where('otp', $otp)->first();
        if(!$user){
            return "Kode verifikasi tidak valid";
        }
        $user->is_verified = "1";
        $user->otp = null;
        $user->updated_at = date('Y-m-d H:i:s');
        $simpan = $user->save();
        
        if(!$simpan){
            return redirect('/continue-registration')->with('error', 'Gagal memverifikasi akun. Silakan coba kode OTP anda.');
        }
        return redirect('/login')->with('success', 'Akun berhasil diverifikasi, silahkan login.');
    }
    
    public function continueRegistrationForm(Request $request)
    {
        return view('auth.continue_registration');
    }
}