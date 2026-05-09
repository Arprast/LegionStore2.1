<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SyaratketentuanController extends Controller
{
     public function index()
    {
        return view('syarat.ketentuan'); // resources/views/tentang/kami.blade.php
    }
}
