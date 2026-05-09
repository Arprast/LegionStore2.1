<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class KebijakanprivasiController extends Controller
{
     public function index()
    {
        return view('kebijakan.privasi'); 
    }
}
