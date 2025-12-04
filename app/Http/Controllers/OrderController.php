<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product_detail;
use App\Models\InventoryLog;
use App\Models\Product;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /* ============================================
     * ADMIN - GET ALL ORDERS
     * ============================================ */
    public function getAll(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || ($user->role ?? '') !== 'admin') {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            $orders = Order::with('user', 'discount')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($orders, 200);

        } catch (\Throwable $e) {
            Log::error('getAll Orders error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }



    /* ============================================
     * USER - MY ORDERS
     * ============================================ */
    public function myOrders()
    {
        $user = auth()->user();

        $orders = Order::with([
            "items.productDetail.product",
            "items.productDetail.color",
            "items.productDetail.size",
            "items.productDetail.images",
        ])
        ->where('user_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($orders);
    }



    /* ============================================
     * ORDER DETAILS
     * ============================================ */
    /* ============================================
 * ORDER DETAILS
 * ============================================ */
public function show(Request $request, $id)
{
    $order = Order::with([
        'user',
        'discount',
        'items.productDetail.product',
        'items.productDetail.color',
        'items.productDetail.size',
        'items.productDetail.images',
    ])->find($id);

    if (!$order) return response()->json(['message' => 'Order not found'], 404);

    $user = $request->user();
    $isAdmin = $user && $user->role === 'admin';

    if (!$isAdmin && $order->user_id !== $user->id) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // ------------------------------------------
    // ⭐ FIX: THÊM FULL_URL CHO IMAGES TRONG ORDER
    // ------------------------------------------
    foreach ($order->items as $item) {
        $pd = $item->productDetail;

        if ($pd && $pd->images) {
            $pd->images = collect($pd->images)->map(function ($img) {

                // If already full URL
                if (preg_match('/^https?:\\/\\//i', $img->url_image)) {
                    $img->full_url = $img->url_image;
                } else {
                    // Build Laravel storage URL
                    $img->full_url = url('storage/' . ltrim($img->url_image, '/'));
                }

                return $img;
            })->values();
        }

        // Fix main product image URL
        if ($pd && $pd->product && $pd->product->image_url) {
            if (!preg_match('/^https?:\\/\\//i', $pd->product->image_url)) {
                $pd->product->image_url = url('storage/' . ltrim($pd->product->image_url, '/'));
            }
        }
    }

    return response()->json($order);
}




    /* ============================================
     * CREATE ORDER
     * ============================================ */
    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'customer.name' => 'required|string',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|string',
            'customer.address' => 'required|string',

            'items' => 'required|array|min:1',
            'items.*.product_detail_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',

            'payment.method' => ['required', Rule::in(['cod','Cash','Banking'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message'=>'Validation failed',
                'errors'=>$validator->errors()
            ],422);
        }

        DB::beginTransaction();

        try {
            $payload = $request->all();
            $items = $payload['items'];
            $user = auth()->user();

            /* ===== CALCULATE TOTAL ===== */
            $total = 0;
            foreach ($items as $it) {
                $pd = Product_detail::find($it['product_detail_id']);
                $total += $pd->price * $it['quantity'];
            }

            /* ===== CREATE ORDER ===== */
            $order = Order::create([
                'user_id' => $user->id,
                'discount_id' => $payload['discount_id'] ?? null,
                'order_code' => Str::uuid(),
                'name' => $payload['customer']['name'],
                'email' => $payload['customer']['email'],
                'phone' => $payload['customer']['phone'],
                'address' => $payload['customer']['address'],
                'note' => $payload['note'] ?? '',
                'total_price' => $total,
                'payment_method' => $payload['payment']['method'] === 'cod' ? 'Cash' : 'Banking',
                'status_stock' => 1,
                'status' => 'pending',
            ]);

            /* ===== CREATE ORDER DETAILS + STOCK UPDATE ===== */
            foreach ($items as $it) {

                $pd = Product_detail::lockForUpdate()->find($it['product_detail_id']);

                if (!$pd) {
                    DB::rollBack();
                    return response()->json(['message'=>'Product detail not found'],422);
                }

                if ($pd->quantity < $it['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'message'=>'Insufficient stock',
                        'product_detail_id'=>$pd->id
                    ],422);
                }

                /* CREATE ORDER DETAIL */
                OrderDetail::create([
                    'order_id' => $order->id,
                    'product_detail_id' => $pd->id,
                    'quantity' => $it['quantity'],
                    'price' => $pd->price,
                ]);

                /* UPDATE STOCK */
                $before = $pd->quantity;
                $pd->quantity -= $it['quantity'];
                $pd->status = $pd->quantity > 0 ? 1 : 0;
                $pd->save();

                /* INVENTORY LOG */
                if (class_exists(InventoryLog::class)) {
                    InventoryLog::create([
                        'product_detail_id' => $pd->id,
                        'change' => -$it['quantity'],
                        'quantity_before' => $before,
                        'quantity_after' => $pd->quantity,
                        'type' => 'order',
                        'related_id' => $order->id,
                        'user_id' => $user->id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Order create error: ".$e->getMessage());
            return response()->json(['message'=>'Server error'],500);
        }
    }



    /* ============================================
     * UPDATE ORDER (ADMIN)
     * ============================================ */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message'=>'Order not found'],404);

        $user = $request->user();
        if (!$user || $user->role !== 'admin') {
            return response()->json(['message'=>'Forbidden'],403);
        }

        $order->update($request->only('status','payment_method','total_price','note'));

        return response()->json([
            'message'=>'Order updated',
            'order'=>$order
        ]);
    }



    /* ============================================
     * DELETE ORDER (ADMIN)
     * ============================================ */
    public function destroy(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message'=>'Order not found'],404);

        if (($request->user()->role ?? '') !== 'admin') {
            return response()->json(['message'=>'Forbidden'],403);
        }

        $order->delete();
        return response()->json(['message'=>'Order deleted']);
    }
}
