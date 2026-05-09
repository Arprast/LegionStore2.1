<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Promo;
use App\Models\ActivityLog;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class PromoController extends Controller
{
    public function index()
    {
        return view('admin.promo.index');
    }

    public function data()
    {
        return DataTables::of(Promo::query())
            ->addIndexColumn()
            ->editColumn('type', function($row){
                return $row->type == 'percent' ? 'Persentase (%)' : 'Nominal (Rp)';
            })
            ->editColumn('value', function($row){
                return $row->type == 'percent' ? $row->value . '%' : 'Rp ' . number_format($row->value, 0, ',', '.');
            })
            ->editColumn('max_discount', function($row){
                return $row->max_discount ? 'Rp ' . number_format($row->max_discount, 0, ',', '.') : '-';
            })
            ->editColumn('quota', function($row){
                return $row->quota ? $row->used . ' / ' . $row->quota : $row->used . ' / Tanpa Batas';
            })
            ->editColumn('valid_until', function($row){
                return $row->valid_until ? Carbon::parse($row->valid_until)->format('d-m-Y') : 'Selamanya';
            })
            ->editColumn('is_active', function($row){
                return $row->is_active 
                    ? '<span style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size:12px;"><i class="fa fa-check"></i> Aktif</span>' 
                    : '<span style="background-color: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size:12px;"><i class="fa fa-times"></i> Nonaktif</span>';
            })
            ->addColumn('action', function($row){
                return '
                <div class="btn-group">
                    <button data-id="'.$row->id.'" class="btn btn-xs btn-warning edit-btn" style="color:white;"><i class="fa fa-edit"></i> Edit</button>
                    <button data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn"><i class="fa fa-trash"></i> Hapus</button>
                </div>
                ';
            })
            ->rawColumns(['action', 'is_active'])
            ->make(true);
    }

    public function store(Request $req)
    {
        $validator = Validator::make($req->all(), [
            'code' => 'required|unique:promos,code',
            'type' => 'required|in:percent,nominal',
            'value' => 'required|numeric|min:1',
            'is_active' => 'required|boolean'
        ]);

        if($validator->fails()){
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        // PERBAIKAN: Gunakan $req->filled() untuk mengubah input kosong jadi null
        $promo = Promo::create([
            'code' => strtoupper($req->code),
            'type' => $req->type,
            'value' => $req->value,
            'max_discount' => $req->filled('max_discount') ? $req->max_discount : null,
            'quota' => $req->filled('quota') ? $req->quota : null,
            'valid_until' => $req->filled('valid_until') ? $req->valid_until : null,
            'is_active' => $req->is_active
        ]);

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'action' => 'Tambah Promo',
            'description' => '['.strtoupper(auth()->user()->role).'] '.auth()->user()->name.' membuat kode promo baru: '.$promo->code
        ]);

        return response()->json(['status' => 'success', 'message' => 'Promo berhasil ditambahkan!']);
    }

    public function edit($id)
    {
        $promo = Promo::find($id);
        if(!$promo) return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        return response()->json(['status' => 'success', 'data' => $promo]);
    }

    public function update(Request $req, $id)
    {
        $validator = Validator::make($req->all(), [
            'code' => 'required|unique:promos,code,'.$id,
            'type' => 'required|in:percent,nominal',
            'value' => 'required|numeric|min:1',
            'is_active' => 'required|boolean'
        ]);

        if($validator->fails()){
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $promo = Promo::find($id);
        // PERBAIKAN: Gunakan $req->filled() untuk mengubah input kosong jadi null
        $promo->update([
            'code' => strtoupper($req->code),
            'type' => $req->type,
            'value' => $req->value,
            'max_discount' => $req->filled('max_discount') ? $req->max_discount : null,
            'quota' => $req->filled('quota') ? $req->quota : null,
            'valid_until' => $req->filled('valid_until') ? $req->valid_until : null,
            'is_active' => $req->is_active
        ]);

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'action' => 'Edit Promo',
            'description' => '['.strtoupper(auth()->user()->role).'] '.auth()->user()->name.' mengubah settingan promo: '.$promo->code
        ]);

        return response()->json(['status' => 'success', 'message' => 'Promo berhasil diupdate!']);
    }

    public function destroy($id)
    {
        $promo = Promo::find($id);
        if(!$promo) return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);

        $code = $promo->code;
        $promo->delete();

        ActivityLog::create([
            'user_id' => auth()->user()->id,
            'action' => 'Hapus Promo',
            'description' => '['.strtoupper(auth()->user()->role).'] '.auth()->user()->name.' MENGHAPUS promo: '.$code
        ]);

        return response()->json(['status' => 'success', 'message' => 'Promo berhasil dihapus!']);
    }
    // Fungsi untuk digunakan pembeli di halaman depan
public function check(Request $req)
{
    $code = strtoupper($req->code);
    $promo = Promo::where('code', $code)->where('is_active', true)->first();

    // 1. Cek keberadaan kode
    if (!$promo) {
        return response()->json(['status' => 'error', 'message' => 'Kode promo tidak ditemukan atau sudah tidak aktif.'], 404);
    }

    // 2. Cek Tanggal Kedaluwarsa
    if ($promo->valid_until && Carbon::now()->gt(Carbon::parse($promo->valid_until))) {
        return response()->json(['status' => 'error', 'message' => 'Waduh, kode promo ini sudah kedaluwarsa!'], 400);
    }

    // 3. Cek Kuota
    if ($promo->quota !== null && $promo->used >= $promo->quota) {
        return response()->json(['status' => 'error', 'message' => 'Maaf, kuota promo ini sudah habis.'], 400);
    }

    // Jika lolos semua cek, kirim data diskonnya
    return response()->json([
        'status' => 'success',
        'message' => 'Kode promo berhasil dipasang!',
        'data' => [
            'type'  => $promo->type,
            'value' => $promo->value,
            'max_discount' => $promo->max_discount
        ]
    ]);
}
}