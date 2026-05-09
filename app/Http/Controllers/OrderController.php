<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        return view('order.track');
    }

    public function track(Request $request)
    {
        $request->validate([
            'invoice' => 'required'
        ]);

        $invoice = $request->invoice;

        // Contoh hasil dummy (nanti bisa ganti query DB)
        return back()->with('success', "Pesanan dengan invoice $invoice ditemukan.");
    }
}
