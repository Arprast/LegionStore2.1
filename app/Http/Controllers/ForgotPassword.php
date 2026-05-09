<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\MailServices;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Models\ForgotPassword as ForgotPasswordM;
use App\Models\User as UserM;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ForgotPassword extends Controller
{
    // ==========================================
    // 1. MENAMPILKAN HALAMAN BLADE
    // ==========================================
    public function recoverPasswordForm()
    {
        // Menampilkan file HTML yang baru saja kita buat.
        // Berdasarkan histori Anda, file tersebut ada di folder 'auth'.
        return view('auth.recover_password'); 
    }

    // ==========================================
    // 2. KIRIM OTP KE EMAIL (Dipanggil via AJAX)
    // ==========================================
    public function sendOtp(Request $request)
    {
        $cek_email = UserM::where('email', $request->input('email'))->first();
        
        if(!$cek_email){
            // Mengembalikan respon JSON agar ditangkap oleh JavaScript
            return response()->json(['success' => false, 'message' => 'Email tidak terdaftar di sistem kami.']);
        }

        $otp = random_int(100000, 999999);
        $req_id = Str::random(10);
        
        $forgot_password = new ForgotPasswordM();
        $forgot_password->user_id = $cek_email->id;
        $forgot_password->otp_code = $otp;
        $forgot_password->req_id = $req_id;
        $forgot_password->created_at = now();
        $simpan = $forgot_password->save();
        
        if(!$simpan){
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data sistem.']);
        }

        // Menggunakan MailServices bawaan Anda yang sudah jalan!
        $to = $cek_email->email;
        $subject = "Permintaan Lupa Password";
        $body = "<h2>Salam!</h2><p>Gunakan kode OTP di bawah ini untuk memulihkan password Anda.</p>";
        $body .= "<h3>Kode OTP: ".$otp."</h3>";
        $body .= "<p>Peringatan: Kode hanya berlaku selama 5 menit.</p>";

        try {
            Mail::to($to)->send(new MailServices($subject, $body));
            return response()->json(['success' => true]); // Sukses!
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Gagal kirim email: ' . $e->getMessage()]);
        }
    }

    // ==========================================
    // 3. VERIFIKASI KODE OTP (Dipanggil via AJAX)
    // ==========================================
    public function verifyOtp(Request $request)
    {
        $cek_email = UserM::where('email', $request->input('email'))->first();
        if(!$cek_email){
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.']);
        }

        // Cari OTP berdasarkan user_id dan kode yang dimasukkan
        $forgot_password = ForgotPasswordM::where('user_id', $cek_email->id)
                                          ->where('otp_code', $request->input('otp'))
                                          ->orderBy('created_at', 'desc')
                                          ->first();

        if(!$forgot_password){
            return response()->json(['success' => false, 'message' => 'Kode OTP tidak valid atau salah.']);
        }

        // Cek Kadaluarsa (5 Menit) sesuai aturan Anda sebelumnya
        $created = Carbon::parse($forgot_password->created_at);
        $menitBerlalu = $created->diffInMinutes(Carbon::now());
        if ($menitBerlalu > 5) {
            return response()->json(['success' => false, 'message' => 'Kode OTP sudah kadaluarsa.']);
        }

        return response()->json(['success' => true]);
    }

    // ==========================================
    // 4. PROSES GANTI PASSWORD (Dipanggil via AJAX)
    // ==========================================
    public function resetPassword(Request $request)
    {
        $cek_email = UserM::where('email', $request->input('email'))->first();
        if(!$cek_email){
            return response()->json(['success' => false, 'message' => 'User tidak ditemukan.']);
        }

        // Cari sesi request lupa password yang masih aktif
        $forgot_password = ForgotPasswordM::where('user_id', $cek_email->id)
                                          ->orderBy('created_at', 'desc')
                                          ->first();

        if(!$forgot_password){
            return response()->json(['success' => false, 'message' => 'Sesi pemulihan tidak valid.']);
        }

        // Cek ulang kadaluarsanya untuk keamanan ganda
        $created = Carbon::parse($forgot_password->created_at);
        if ($created->diffInMinutes(Carbon::now()) > 5) {
            return response()->json(['success' => false, 'message' => 'Sesi telah kadaluarsa. Ulangi dari awal.']);
        }

        // Ganti password user dengan yang baru
        $cek_email->password = Hash::make($request->input('password'));
        $simpan = $cek_email->save();

        if(!$simpan){
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui password.']);
        }

        // Hapus history OTP agar tidak bisa disalahgunakan lagi
        ForgotPasswordM::where('user_id', $cek_email->id)->delete();

        return response()->json(['success' => true]);
    }
}