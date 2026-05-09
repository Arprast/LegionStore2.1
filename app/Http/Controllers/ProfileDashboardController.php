<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Game;
use App\Models\User;
use App\Models\ProductPurchases;
use App\Models\WalletMutation; // <--- TAMBAHAN: Import Model Mutasi
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\DataTables;

class ProfileDashboardController extends Controller
{
    public function index(){
        $data=User::where('id',auth()->user()->id)->first();
        $totalTransactions=ProductPurchases::where('user_id',auth()->user()->id)->count();
        $todayTransactions=ProductPurchases::where('user_id',auth()->user()->id)->whereDate('created_at',date('Y-m-d'))->count();
        $thisYearTransactions=ProductPurchases::where('user_id',auth()->user()->id)->whereYear('created_at',date('Y'))->count();
        $thisMonthTransactions=ProductPurchases::where('user_id',auth()->user()->id)->whereYear('created_at',date('Y'))->whereMonth('created_at',date('m'))->count();
        $pendingTransaction=ProductPurchases::where('user_id',auth()->user()->id)->where('status','pending')->count();
        $failedTransactions=ProductPurchases::where('user_id',auth()->user()->id)->where('status','failed')->count();
        $successTransactions=ProductPurchases::where('user_id',auth()->user()->id)->where('status','success')->count();
        
        // --- TAMBAHAN: Ambil Riwayat Mutasi Saldo ---
        $mutations = WalletMutation::where('user_id', auth()->user()->id)
                        ->orderBy('created_at', 'desc')
                        ->get();
        // -------------------------------------------
        
        return view('profile_dashboard', compact(
            'data',
            'todayTransactions',
            'thisMonthTransactions',
            'thisYearTransactions',
            'totalTransactions',
            'pendingTransaction',
            'failedTransactions',
            'successTransactions',
            'mutations' // <--- Jangan lupa kirim variabel ini ke view
        ));
    }
}