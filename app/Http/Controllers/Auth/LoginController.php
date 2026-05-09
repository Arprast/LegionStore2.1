<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        if($request->has('forgot_password')){
            return redirect()->to(url('/forgot-password?email='.$request->input('login')));
        }

        $request->validate([
            'login' => ['required'],
            'password' => ['required'],
        ]);

        $login = $request->input('login');
        $password = $request->input('password');
        
        // cek apakah input berupa email atau username (name)
        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $credential = [
            $fieldType => $login,
            'password' => $password
        ];
        
        $remember = $request->has('remember');

        if (Auth::attempt($credential, $remember)) {
            $user = Auth::user(); // ambil user yang login
            
            // BAGIAN PENGECEKAN is_verified TELAH DIHAPUS DI SINI
            // Sehingga siapapun yang berhasil login akan langsung masuk

            if ($user->role === 'admin') {
                return redirect('/admin');
            }
            
            // Jika customer, arahkan ke beranda
            return redirect()->intended('/');
        }

        return redirect()->back()->with('error', 'Email atau password salah!');
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }
}
