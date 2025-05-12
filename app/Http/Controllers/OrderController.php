<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    protected $midtrans;

    public function __construct(MidtransService $midtrans)
    {
        $this->midtrans = $midtrans;
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string',
            'email' => 'required|email',
            'amount' => 'required|integer|min:1000',
        ]);

        // Buat order baru
        $order = Order::create([
            'order_code' => strtoupper(Str::random(10)),
            'name' => $request->name,
            'email' => $request->email,
            'amount' => $request->amount,
        ]);

        // Data untuk Midtrans Snap
        $params = [
            'transaction_details' => [
                'order_id' => $order->order_code,
                'gross_amount' => $order->amount,
            ],
            'customer_details' => [
                'first_name' => $order->name,
                'email' => $order->email,
            ],
        ];

        $snap = $this->midtrans->createTransaction($params);

        return response()->json([
            'message' => 'Order created',
            'order' => $order,
            'snap_token' => $snap->token,
            'snap_url' => $snap->redirect_url,
        ]);
    }

    // Endpoint untuk menerima webhook dari Midtrans (optional tapi disarankan)
    public function webhook(Request $request)
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? null;

        if (!$orderId) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $order = Order::where('order_code', $orderId)->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        // Update status pembayaran
        $transactionStatus = $payload['transaction_status'];

        if ($transactionStatus === 'settlement' || $transactionStatus === 'capture') {
            $order->payment_status = 'paid';
        } elseif (in_array($transactionStatus, ['cancel', 'expire', 'deny'])) {
            $order->payment_status = 'failed';
        } else {
            $order->payment_status = 'pending';
        }

        $order->midtrans_response = $payload;
        $order->save();

        return response()->json(['message' => 'Webhook handled']);
    }
}
