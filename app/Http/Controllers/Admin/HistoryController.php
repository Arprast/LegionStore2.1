<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductPurchases;
use App\Models\Topup; // <-- INI YANG TADI KETINGGALAN BOSKU
use Illuminate\Support\Facades\Hash;
use Validator;
use Str;
use Illuminate\Support\Facades\Storage;
use App\Services\InvoiceGenerator;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class HistoryController extends Controller
{
    public function index(){
        return view('admin.history.index');
    }

    public function data()
    {
        return DataTables::of(ProductPurchases::with('users'))
            ->addIndexColumn()
            ->addColumn('username', function ($row) {
                  return $row->users->name ?? '-';
              })
            ->editColumn('created_at', function ($row) {
                  return Carbon::parse($row->created_at)
                      ->timezone('Asia/Jakarta')
                      ->format('d-m-Y H:i');
              })
              ->editColumn('completed_at', function ($row) {
                  return $row->completed_at ? Carbon::parse($row->completed_at)
                      ->timezone('Asia/Jakarta')
                      ->format('d-m-Y H:i') : '-';
              })
            // ==========================================
            // FIX BUG STATUS: Pastikan membaca kolom $row->status dengan benar
            // ==========================================
            ->editColumn('status', function ($row) {
                // Konversi ke lowercase untuk amannya
                $status = strtolower($row->status);
                
                if($status == 'success'){
                    return '<span style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size:12px; font-weight:bold;"><i class="fa fa-check-circle"></i> Sukses</span>';
                } else if($status == 'pending'){
                    return '<span style="background-color: #f7b035; color: black; padding: 4px 8px; border-radius: 4px; font-size:12px; font-weight:bold;"><i class="fa fa-clock-o"></i> Pending</span>';
                } else {
                    return '<span style="background-color: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size:12px; font-weight:bold;"><i class="fa fa-times-circle"></i> Batal</span>';
                }
            })
            // ==========================================
            // ROMBAK UI: TOMBOL AKSI DENGAN ICON
            // ==========================================
            ->addColumn('action', function ($row) {
                $status = strtolower($row->status);
              // Jika status masih PENDING
              $option='
                  <div class="btn-group">
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-success process-btn" style="background-color: #28a745; border:none;"><i class="fa fa-check"></i> Proses</a>
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-warning cancel-btn" style="background-color: #dc3545; color:white; border:none;"><i class="fa fa-times"></i> Batal</a>
                  </div>
                ';
                
                // Jika status SUKSES
                if($status=="success"){
                  $option='<div class="btn-group">
                    <a href="/admin/riwayat-pembelian/cetak-struk/'.$row->id.'" class="btn btn-xs btn-info print-btn" target="_blank" style="background-color: #17a2b8; border:none;"><i class="fa fa-print"></i> Struk</a>
                  </div>';
                }
              // Jika status GAGAL / CANCEL
                else if(($status=="failed")||($status=="cancel")){
                  // Ambil alasan dari database, kalau kosong beri teks default
                  $alasanBatal = $row->cancel_reason ? htmlspecialchars($row->cancel_reason, ENT_QUOTES) : 'Dibatalkan oleh sistem / API pihak ketiga tanpa keterangan.';
                  
                  $option='<div class="btn-group">
                    <a data-reason="'.$alasanBatal.'" class="btn btn-xs btn-info view-reason-btn" style="background-color: #17a2b8; border:none; color:white;"><i class="fa fa-info-circle"></i> Alasan</a>
                    <a data-id="'.$row->id.'" class="btn btn-xs btn-danger del-btn" style="background-color: #343a40; border:none; color:white;"><i class="fa fa-trash"></i> Hapus</a>                    
                  </div>';
                }
                return $option; // JANGAN LUPA ME-RETURN VARIABEL $option
            })
            // WAJIB DITAMBAHKAN: Agar HTML label status bisa terbaca
            ->rawColumns(['action', 'status']) 
            ->make(true);
    }

    // TAMBAHKAN FUNGSI INI DI BAWAH fungsi data()
    public function dataCoin()
    {
        return DataTables::of(Topup::with('user'))
            ->addIndexColumn()
            ->addColumn('username', function ($row) {
                  return $row->user->name ?? '-';
              })
            ->editColumn('amount', function ($row) {
                  return 'Rp ' . number_format($row->amount, 0, ',', '.');
              })
            ->editColumn('created_at', function ($row) {
                  return Carbon::parse($row->created_at)
                      ->timezone('Asia/Jakarta')
                      ->format('d-m-Y H:i');
              })
            ->editColumn('status', function ($row) {
                $status = strtolower($row->status);
                if($status == 'success'){
                    return '<span style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size:12px; font-weight:bold;"><i class="fa fa-check-circle"></i> Sukses</span>';
                } else if($status == 'pending'){
                    return '<span style="background-color: #f7b035; color: black; padding: 4px 8px; border-radius: 4px; font-size:12px; font-weight:bold;"><i class="fa fa-clock-o"></i> Pending</span>';
                } else {
                    return '<span style="background-color: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; font-size:12px; font-weight:bold;"><i class="fa fa-times-circle"></i> Batal</span>';
                }
            })
            ->rawColumns(['status']) 
            ->make(true);
    }

    public function prosesCancelOrder(Request $req,$id){
      $validator = Validator::make($req->all(),[
          'cancel_reason'     => 'required|string|max:255',
        ]);
    
        if ($validator->fails()) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
        $dataLama = ProductPurchases::find($id);
        if (!$dataLama) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ["cancel-reason"=>["data tidak ada"]]
            ], 422);
        }
        $ProductPurchases=ProductPurchases::find($id);
        $ProductPurchases->cancel_reason=$req->cancel_reason;
        $ProductPurchases->status="cancel";
        $simpan=$ProductPurchases->save();
        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Pembelian berhasil dibatalkan',
                'data' => $ProductPurchases
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal mengedit user, silakan coba lagi'
          ], 500);
    }

    public function processOrder($id){
        // [PERBAIKAN RELASI]: products.product_category.game
        $dataLama = ProductPurchases::with('products.product_category.game','users')->find($id);
        
        if (!$dataLama) {
          return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => ["cancel-reason"=>["data tidak ada"]]
            ], 422);
        }

        $cekNomorInvoice=ProductPurchases::select('completed_at','invoice')->where('status','success')->whereDate('completed_at',date('Y-m-d'))
        ->orderByRaw(
          'CAST(RIGHT(invoice, 4) AS UNSIGNED) ASC'
      )
        ->first();

        $ProductPurchases=ProductPurchases::find($id);
        $ProductPurchases->status="success";
        
        $ProductPurchases->completed_at=date('Y-m-d H:i:s');
        $simpan=$ProductPurchases->save();
        if($simpan){
            return response()->json([
                'status' => 'success',
                'message' => 'Pembelian berhasil diproses',
                'data' => $ProductPurchases
            ], 200);
          }
          return response()->json([
              'status' => 'error',
              'message' => 'Gagal mengedit user, silakan coba lagi'
          ], 500);
    }

    public function getOne($id){
      $data=ProductPurchases::find($id);
      if(!$data){
        return response()->json([
                'status' => 'error',
                'message' => 'Data Tidak Ada']
            , 404);
      }
      return response()->json([
          'status' => 'success',
          'data'=>$data]
      , 200); 
    }

    public function printInvoice($id){
      $invoiceGenerator = new InvoiceGenerator();
      // [PERBAIKAN RELASI]: products.product_category.game
      $data=ProductPurchases::with('products.product_category.game','users')->find($id);
      if(!$data){
        return response()->json([
                'status' => 'error',
                'message' => 'Data Tidak Ada']
            , 404);
      }

       $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        // HTML dari blade
        $html = view('invoice',compact('data'))->render();

        $dompdf->loadHtml($html);

        // Set ukuran kertas
        $paperWidth  = 226;   // 80mm
        $paperHeight = 1000;  // bebas, nanti auto cut

        $dompdf->setPaper([0,0,$paperWidth, $paperHeight]);

        // Render PDF
        $dompdf->render();

        // Tampilkan di browser
        return $dompdf->stream($data->order_id.'.pdf', [
            'Attachment' => true 
        ]);
    }

    public function remove(Request $req,$id){
        $Product=ProductPurchases::find($id);
        if (!$Product) {
          return response()->json([
              'status' => 'error',
              'message' => 'produk tidak ada'
          ], 404);
        }
        $simpan=$Product->delete();
        if($simpan){
          return response()->json([
                'status' => 'success',
                'message' => 'Produk berhasil dihapus'
            ], 200);
        }
    }
}