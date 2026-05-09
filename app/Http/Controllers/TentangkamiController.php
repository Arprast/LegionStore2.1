<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TentangkamiController extends Controller
{
    public function index()
    {
        return view('tentang.kami'); // resources/views/tentang/kami.blade.php
    }
}