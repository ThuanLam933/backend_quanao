<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Exchange;
use App\Models\ExchangeDetail;
use Illuminate\Support\Facades\DB;
use App\Models\Product_detail;
use App\Models\InventoryLog;


class ExchangeController extends Controller
{
    public function index()
    {
        $exchanges = Exchange::with(['exchangeDetails', 'user:id,name,email'])
        ->orderByDesc('create_exchange')
        ->orderByDesc('id')
        ->get();
        return response()->json($exchanges);
    }

    public function show($id)
    {
        $exchange = Exchange::with(['exchangeDetails', 'user:id,name,email'])->find($id);
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
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected,in_transit,completed,cancelled',
            'note'   => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $id, $request) {
                $exchange = Exchange::with('exchangeDetails')
                    ->lockForUpdate()
                    ->find($id);

                if (!$exchange) {
                    return response()->json(['message' => 'Exchange not found'], 404);
                }

                $oldStatus = strtolower((string) $exchange->status);
                $newStatus = strtolower((string) $validated['status']);

                // APPROVED: trừ tồn sản phẩm mới
                $shouldDeductNew = ($oldStatus !== 'approved' && $newStatus === 'approved');

                // COMPLETED: cộng tồn sản phẩm cũ
                $shouldAddOld = ($oldStatus !== 'completed' && $newStatus === 'completed');

                // (khuyến nghị) chặn completed khi chưa approved/in_transit
                if ($newStatus === 'completed' && !in_array($oldStatus, ['approved', 'in_transit'], true)) {
                    throw new \RuntimeException('Chỉ có thể hoàn thành sau khi đã duyệt/đang vận chuyển.');
                }

                $userId = $request->user() ? $request->user()->id : null;

                // 1) APPROVED -> trừ product_new_id
                if ($shouldDeductNew) {
                    foreach ($exchange->exchangeDetails as $d) {
                        $qty = (int)($d->quantity ?? 0);
                        if ($qty <= 0) continue;

                        $newPdId = $d->product_new_id;
                        $pd = Product_detail::lockForUpdate()->find($newPdId);
                        if (!$pd) {
                            throw new \RuntimeException("Không tìm thấy product_detail mới (ID: {$newPdId}).");
                        }

                        $before = (int)($pd->quantity ?? 0);
                        $after = $before - $qty;

                        if ($after < 0) {
                            throw new \RuntimeException("Không đủ tồn kho cho sản phẩm mới (ID: {$newPdId}).");
                        }

                        $pd->quantity = $after;
                        $pd->save();

                        InventoryLog::create([
                            'product_detail_id' => $pd->id,
                            'change' => -$qty,
                            'quantity_before' => $before,
                            'quantity_after' => $after,
                            'type' => 'exchange_approved',   // type mới
                            'related_id' => $exchange->id,   // gắn exchange id
                            'user_id' => $userId,
                            
                        ]);
                    }
                }

                // 2) COMPLETED -> cộng product_old_id
                if ($shouldAddOld) {
                    foreach ($exchange->exchangeDetails as $d) {
                        $qty = (int)($d->quantity ?? 0);
                        if ($qty <= 0) continue;

                        $oldPdId = $d->product_old_id;
                        $pd = Product_detail::lockForUpdate()->find($oldPdId);
                        if (!$pd) {
                            throw new \RuntimeException("Không tìm thấy product_detail cũ (ID: {$oldPdId}).");
                        }

                        $before = (int)($pd->quantity ?? 0);
                        $after = $before + $qty;

                        $pd->quantity = $after;
                        $pd->save();

                        InventoryLog::create([
                            'product_detail_id' => $pd->id,
                            'change' => +$qty,
                            'quantity_before' => $before,
                            'quantity_after' => $after,
                            'type' => 'exchange_completed',  // type mới
                            'related_id' => $exchange->id,
                            'user_id' => $userId,
                            
                        ]);
                    }
                }

                // Update exchange
                $exchange->status = $newStatus;
                if (array_key_exists('note', $validated)) {
                    $exchange->note = $validated['note'] ?? '';
                }
                $exchange->save();

                return response()->json([
                    'message' => 'Cập nhật exchange thành công.',
                    'exchange' => $exchange->fresh()->load('exchangeDetails'),
                    'inventory' => [
                        'deduct_new_on_approved' => $shouldDeductNew,
                        'add_old_on_completed' => $shouldAddOld,
                    ],
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Exchange update error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server khi cập nhật exchange.'], 500);
        }
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
