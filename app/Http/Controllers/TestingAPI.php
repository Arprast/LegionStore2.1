<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class TestingAPI extends Controller
{
    public function sendData()
    {
        $username = config('app.digiflazz_username');
        $apiKey   = config('app.digiflazz_api_key');
        // $apiKey="dev-7f7602c0-0bd6-11f1-936f-d5c97b067777";

        $ref_id = "INV" . time(); // harus unik

        $sign = md5($username . $apiKey . $ref_id);

        $customer_no = "833545052asia"; // format untuk cek username: 833545052os_asia (customer_no = zone_id_username)

        $payload = [
            'username'       => $username,
            'buyer_sku_code' => 'GI980',
            'customer_no'    => $customer_no,
            'ref_id'         => $ref_id,
            'sign'           => $sign,
            // 'testing'        => true, // aktifkan jika mode development
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post('https://api.digiflazz.com/v1/transaction', $payload);

        return response()->json([
            'payload' => $payload,
            'result' => $response->json(),
            'status_code' => $response->status(),
        ]);
    }
    public function testPriceList()
    {
        $username = config('app.digiflazz_username');
        $apikey   = config('app.digiflazz_api_key');

        $sign = md5($username . $apikey . "pricelist");
        $payload=[
            "cmd" => "prepaid",
            "username" => $username,
            "sign" => $sign
        ];
        $response = Http::post("https://api.digiflazz.com/v1/price-list", $payload);
        return response()->json([
                'payload' => $payload,
                'result' => $response->json(),
                'status_code' => $response->status(),
            ]);
        // return response()->json($response->json());
    }
    public function CekUsernameMobileLegends()
    {
        $username = config('app.digiflazz_username');
        $apiKey   = config('app.digiflazz_api_key');
        // $apiKey="dev-7f7602c0-0bd6-11f1-936f-d5c97b067777";

        $ref_id = "INV" . time(); // harus unik

        $sign = md5($username . $apiKey . $ref_id);

        $customer_no = "428755163_99689"; // format untuk cek username ML: 428755163_99689 (customer_no = zone_id_username)

        $payload = [
            'username'       => $username,
            'buyer_sku_code' => 'MLCU',
            'customer_no'    => $customer_no,
            'ref_id'         => $ref_id,
            'sign'           => $sign,
            // 'testing'        => true, // aktifkan jika mode development
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->post('https://api.digiflazz.com/v1/transaction', $payload);

        return response()->json([
            'payload' => $payload,
            'result' => $response->json(),
            'status_code' => $response->status(),
        ]);
    }
    public function checkStatus($ref_id)
    {
        $username = config('app.digiflazz_username');
        $apiKey   = config('app.digiflazz_api_key');

        $sign = md5($username . $apiKey . $ref_id);

        $response = Http::post('https://api.digiflazz.com/v1/transaction', [
            'username'        => $username,
            'ref_id'          => $ref_id,
            'sign'            => $sign,
            'cmd'             => 'status'
        ]);

        return $response->json();
    }
}