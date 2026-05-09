<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FlashSale;
use App\Models\Game;
use App\Models\ProductCategory;
use App\Models\Product;
use Validator;
use Yajra\DataTables\DataTables;

class FlashSaleController extends Controller
{
    // =========================================
    // 1. TAMPILAN UTAMA & DATATABLES
    // =========================================
    public function indexMaster()
    {
        // Cek status kuncian global (Ambil dari data pertama saja karena dikunci serentak)
        $isLocked = FlashSale::where('is_locked', '1')->exists();
        return view('admin.flash_sale.index_master', compact('isLocked'));
    }

    public function dataMaster()
    {
        $query = FlashSale::with(['product.product_category.game']);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('game_name', function($row) {
                return $row->product->product_category->game->name ?? '-';
            })
            ->addColumn('product_name', function($row) {
                return $row->product->name ?? '-';
            })
            ->addColumn('normal_price', function($row) {
                return 'Rp ' . number_format($row->normal_price, 0, ',', '.');
            })
            ->addColumn('flash_price', function($row) {
                return 'Rp ' . number_format($row->price, 0, ',', '.');
            })
            ->addColumn('action', function ($row) {
                // Jika sedang dikunci, hilangkan tombol edit/hapus
                if ($row->is_locked == '1') {
                    return '<span class="badge badge-danger"><i class="fa fa-lock"></i> Terkunci</span>';
                }

                $actionButton = '
                    <div class="btn-group">
                        <a href="javascript:void(0)" data-id="'.$row->id.'" class="btn btn-xs btn-warning edit-btn"><i class="fa fa-edit"></i> Edit</a>
                        <a href="javascript:void(0)" data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn"><i class="fa fa-trash"></i> Hapus</a>
                    </div>
                ';
                return $actionButton;
            })
            ->rawColumns(['action', 'game_name', 'product_name'])
            ->make(true);
    }

    // =========================================
    // 2. FITUR KUNCI / BUKA (LOCK TOGGLE)
    // =========================================
    public function toggleLock(Request $req)
    {
        $lockStatus = $req->status; // Menerima '1' (Kunci) atau '0' (Buka)
        
        // Update seluruh data flash sale
        FlashSale::query()->update(['is_locked' => $lockStatus]);

        $message = $lockStatus == '1' ? 'Data Flash Sale Berhasil Dikunci!' : 'Data Flash Sale Terbuka. Anda bisa mengedit kembali.';
        
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'is_locked' => $lockStatus == '1'
        ]);
    }

    // =========================================
    // 3. AJAX PENCARIAN BERTINGKAT (DROPDOWN)
    // =========================================
    public function searchGame(Request $request)
    {
        $search = $request->q;
        $games = Game::where('name', 'LIKE', "%$search%")->limit(10)->get();
        return response()->json($games);
    }

    public function getCategories($game_id)
    {
        $categories = ProductCategory::where('game_id', $game_id)->get();
        return response()->json($categories);
    }

    public function getProducts($category_id)
    {
        $products = Product::where('product_category_id', $category_id)->get();
        return response()->json($products);
    }

    // =========================================
    // 4. CRUD OPERASIONAL
    // =========================================
    public function prosesAddMaster(Request $req)
    {
        $validator = Validator::make($req->all(),[
            'product_id' => 'required',
            'stock'      => 'required|integer|min:1',
            'price'      => 'required|integer|min:0',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        // Cek apakah data global sedang dikunci
        if (FlashSale::where('is_locked', '1')->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Gagal. Data Flash Sale sedang dalam posisi Terkunci.'], 403);
        }

        $product = Product::find($req->product_id);
        if (!$product) {
            return response()->json(['status' => 'error', 'message' => 'Produk tidak ditemukan'], 404);
        }

        // Cek duplikasi (Jangan sampai 1 item didaftarkan 2 kali di flash sale)
        if (FlashSale::where('product_id', $req->product_id)->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Produk ini sudah ada di daftar Flash Sale.'], 409);
        }

        $fs = new FlashSale;
        $fs->product_id   = $req->product_id;
        $fs->normal_price = $product->selling_price; // Simpan harga aslinya saat ini
        $fs->price        = $req->price; // Harga flash sale
        $fs->stock        = $req->stock;
        $fs->qty          = 1; // Default
        $fs->is_locked    = '0';
        $fs->save();

        return response()->json(['status' => 'success', 'message' => 'Item berhasil ditambahkan ke Flash Sale.']);
    }

    public function getOneMaster($id)
    {
        $data = FlashSale::with(['product.product_category.game'])->find($id);
        if(!$data) return response()->json(['status' => 'error', 'message' => 'Data tidak ditemukan'], 404);
        
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function prosesEditMaster(Request $req, $id)
    {
        $validator = Validator::make($req->all(),[
            'stock' => 'required|integer|min:1',
            'price' => 'required|integer|min:0',
        ]);
    
        if ($validator->fails()) return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);

        $fs = FlashSale::find($id);
        if (!$fs) return response()->json(['status' => 'error', 'message' => 'Data tidak ada'], 404);

        if ($fs->is_locked == '1') {
            return response()->json(['status' => 'error', 'message' => 'Gagal. Item sedang dikunci.'], 403);
        }

        $fs->price = $req->price;
        $fs->stock = $req->stock;
        $fs->save();

        return response()->json(['status' => 'success', 'message' => 'Perubahan berhasil disimpan.']);
    }

    public function removeMaster($id)
    {
        $fs = FlashSale::find($id);
        if (!$fs) return response()->json(['status' => 'error', 'message' => 'Data tidak ada'], 404);

        if ($fs->is_locked == '1') {
            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus. Item sedang dikunci.'], 403);
        }

        $fs->delete();
        return response()->json(['status' => 'success', 'message' => 'Item berhasil dihapus.']);
    }
}