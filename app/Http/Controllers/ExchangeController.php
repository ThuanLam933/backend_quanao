<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Exchange;
use App\Models\ExchangeDetail;

class ExchangeController extends Controller
{
    public function index()
    {
        $exchanges = Exchange::with('exchangeDetails')->get();
        return response()->json($exchanges);
    }

    public function show($id)
    {
        $exchange = Exchange::with('exchangeDetails')->find($id);
        if (!$exchange) {
            return response()->json(['message' => 'Exchange not found'], 404);
        }
        return response()->json($exchange);
    }

    public function userExchanges($userId)
    {
        $exchanges = Exchange::with('exchangeDetails')->where('user_id', $userId)->get();
        return response()->json($exchanges);
    }
    
    /**
     * API tạo báo cáo đổi trả sản phẩm
     * Chỉ cho phép nếu đơn hàng có trạng thái hoàn thành
     */
    public function store(Request $request)
    {
        Log::info('Creating product exchange report', ['data' => $request->all()]);

        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'user_id' => 'required|exists:users,id',
            'note' => 'nullable|string',
            'exchange_details' => 'required|array|min:1',
            'exchange_details.*.product_detail_id' => 'required|exists:product_details,id',
            'exchange_details.*.quantity' => 'required|integer|min:1',
            'exchange_details.*.reason' => 'nullable|string',
            'exchange_details.*.product_old_id' => 'required|exists:product_details,id',
            'exchange_details.*.product_new_id' => 'required|exists:product_details,id',
        ]);

        // Kiểm tra trạng thái đơn hàng
        $order = Order::find($validated['order_id']);
        if (!$order || $order->status !== 'completed') {
            return response()->json(['message' => 'Chỉ được đổi trả khi đơn hàng đã hoàn thành.'], 422);
        }

        // Tạo Exchange
        $exchange = Exchange::create([
            'order_id' => $validated['order_id'],
            'user_id' => $validated['user_id'],
            'note' => $validated['note'] ?? '',
            'status' => 'pending',
            'create_exchange' => now(),
        ]);

        // Tạo ExchangeDetail cho từng sản phẩm đổi trả
        foreach ($validated['exchange_details'] as $item) {
            ExchangeDetail::create([
                'exchange_id' => $exchange->id,
                'product_detail_id' => $item['product_detail_id'],
                'quantity' => $item['quantity'],
                'reason' => $item['reason'] ?? '',
                'product_old_id' => $item['product_old_id'],
                'product_new_id' => $item['product_new_id'],
            ]);
        }

        return response()->json([
            'message' => 'Tạo báo cáo đổi trả thành công.',
            'exchange' => $exchange->load('exchangeDetails')
        ], 201);
    }
}
