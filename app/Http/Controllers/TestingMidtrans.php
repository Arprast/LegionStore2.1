<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\CoreApi;

class TestingMidtrans extends Controller
{
    public function __construct()
    {
        Config::$serverKey = app('midtrans_server_key');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function qrisPage()
    {
        $order_id = "QRIS-" . time();
        $gross_amount = 10000;

        $params = [
            'transaction_details' => [
                'order_id' => $order_id,
                'gross_amount' => $gross_amount,
            ],
            'customer_details' => [
                'first_name' => 'User',
                'email' => 'test@gmail.com',
                'phone' => '08123456789',
            ],
            'item_details' => [
                [
                    'id' => 'QRIS001',
                    'price' => $gross_amount,
                    'quantity' => 1,
                    'name' => 'Topup Game QRIS Testing'
                ]
            ],
            // optional: supaya lebih fokus QRIS
            // 'enabled_payments' => ['other_qris','BRI VA','BNI VA','BCA VA']
        ];

        $snapToken = Snap::getSnapToken($params);

        return view('midtrans.qris', compact('snapToken', 'order_id', 'gross_amount'));
    }
    public function vaPage()
    {

        $order_id = "VA-" . time();
        $gross_amount = 10000;

        $params = [
            "payment_type" => "bank_transfer",
            "transaction_details" => [
                "order_id" => $order_id,
                "gross_amount" => $gross_amount
            ],
            "bank_transfer" => [
                "bank" => "bca"
            ],
            "customer_details" => [
                "first_name" => "User",
                "email" => "test@gmail.com",
                "phone" => "08123456789"
            ],
            "item_details" => [
                [
                    "id" => "VA001",
                    "price" => $gross_amount,
                    "quantity" => 1,
                    "name" => "Topup Game VA Testing"
                ]
            ]
        ];

        $response = CoreApi::charge($params);

        // ambil VA number
        $va_number = $response->va_numbers[0]->va_number ?? null;
        $bank = $response->va_numbers[0]->bank ?? null;

        return view('midtrans.va', compact('order_id', 'gross_amount', 'va_number', 'bank'));
    }
}
