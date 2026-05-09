<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductPurchases;
use App\Models\Topup; 
use Carbon\Carbon;

class LaporanController extends Controller
{
    public function index(Request $request)
    {
        // Tangkap filter bulan & tahun
        $bulan = $request->input('bulan', date('m'));
        $tahun = $request->input('tahun', date('Y'));

        // =========================================================
        // 1. KOTAK REKAP DANA GLOBAL (Keseluruhan)
        // =========================================================
        $trackModal = ProductPurchases::where('status', 'success')
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)->sum('harga_modal');

        $trackKeuntungan = ProductPurchases::where('status', 'success')
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)->sum('keuntungan_bersih');

        $trackAdmin = ProductPurchases::where('status', 'success')
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)->sum('biaya_admin');

        $trackTotalTransaksi = ProductPurchases::where('status', 'success')
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)->sum('price');

        // =========================================================
        // 2. STATISTIK PEMBELIAN ITEM GAME
        // =========================================================
        $beliSukses = ProductPurchases::where('status', 'success')
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)->count();
            
        $beliGagal = ProductPurchases::whereIn('status', ['failed', 'cancel'])
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)->count();

        $beliGuest = ProductPurchases::where('status', 'success')->whereNull('user_id')
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)->count();
            
        $beliCustomer = ProductPurchases::join('users', 'product_purchases.user_id', '=', 'users.id')
            ->where('product_purchases.status', 'success')->where('users.role', 'customer')
            ->whereMonth('product_purchases.created_at', $bulan)->whereYear('product_purchases.created_at', $tahun)->count();
            
        $beliMembership = ProductPurchases::join('users', 'product_purchases.user_id', '=', 'users.id')
            ->where('product_purchases.status', 'success')->where('users.role', 'membership')
            ->whereMonth('product_purchases.created_at', $bulan)->whereYear('product_purchases.created_at', $tahun)->count();
            
        $beliMitra = ProductPurchases::join('users', 'product_purchases.user_id', '=', 'users.id')
            ->where('product_purchases.status', 'success')->where('users.role', 'mitra')
            ->whereMonth('product_purchases.created_at', $bulan)->whereYear('product_purchases.created_at', $tahun)->count();

        // =========================================================
        // 3. STATISTIK TOP UP SALDO WALLET
        // =========================================================
        $topupSukses = Topup::where('status', 'success')
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)->count();
            
        $topupGagal = Topup::whereIn('status', ['failed', 'cancel'])
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)->count();

        $topupCustomer = Topup::join('users', 'topups.user_id', '=', 'users.id')
            ->where('topups.status', 'success')->where('users.role', 'customer')
            ->whereMonth('topups.created_at', $bulan)->whereYear('topups.created_at', $tahun)->count();
            
        $topupMembership = Topup::join('users', 'topups.user_id', '=', 'users.id')
            ->where('topups.status', 'success')->where('users.role', 'membership')
            ->whereMonth('topups.created_at', $bulan)->whereYear('topups.created_at', $tahun)->count();
            
        $topupMitra = Topup::join('users', 'topups.user_id', '=', 'users.id')
            ->where('topups.status', 'success')->where('users.role', 'mitra')
            ->whereMonth('topups.created_at', $bulan)->whereYear('topups.created_at', $tahun)->count();

        // =========================================================
        // 4. REKAP UANG BERDASARKAN ROLE (BARU)
        // =========================================================
        $uangGuest = ProductPurchases::where('status', 'success')->whereNull('user_id')
            ->whereMonth('created_at', $bulan)->whereYear('created_at', $tahun)
            ->selectRaw('SUM(harga_modal) as modal, SUM(keuntungan_bersih) as untung, SUM(biaya_admin) as admin, SUM(price) as kotor')->first();

        $uangCustomer = ProductPurchases::join('users', 'product_purchases.user_id', '=', 'users.id')
            ->where('product_purchases.status', 'success')->where('users.role', 'customer')
            ->whereMonth('product_purchases.created_at', $bulan)->whereYear('product_purchases.created_at', $tahun)
            ->selectRaw('SUM(product_purchases.harga_modal) as modal, SUM(product_purchases.keuntungan_bersih) as untung, SUM(product_purchases.biaya_admin) as admin, SUM(product_purchases.price) as kotor')->first();

        $uangMembership = ProductPurchases::join('users', 'product_purchases.user_id', '=', 'users.id')
            ->where('product_purchases.status', 'success')->where('users.role', 'membership')
            ->whereMonth('product_purchases.created_at', $bulan)->whereYear('product_purchases.created_at', $tahun)
            ->selectRaw('SUM(product_purchases.harga_modal) as modal, SUM(product_purchases.keuntungan_bersih) as untung, SUM(product_purchases.biaya_admin) as admin, SUM(product_purchases.price) as kotor')->first();

        $uangMitra = ProductPurchases::join('users', 'product_purchases.user_id', '=', 'users.id')
            ->where('product_purchases.status', 'success')->where('users.role', 'mitra')
            ->whereMonth('product_purchases.created_at', $bulan)->whereYear('product_purchases.created_at', $tahun)
            ->selectRaw('SUM(product_purchases.harga_modal) as modal, SUM(product_purchases.keuntungan_bersih) as untung, SUM(product_purchases.biaya_admin) as admin, SUM(product_purchases.price) as kotor')->first();

        return view('admin.laporan.index', compact(
            'bulan', 'tahun', 'trackModal', 'trackKeuntungan', 'trackAdmin', 'trackTotalTransaksi',
            'beliSukses', 'beliGagal', 'beliGuest', 'beliCustomer', 'beliMembership', 'beliMitra',
            'topupSukses', 'topupGagal', 'topupCustomer', 'topupMembership', 'topupMitra',
            'uangGuest', 'uangCustomer', 'uangMembership', 'uangMitra'
        ));
    }
}