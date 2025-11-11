<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CartDetailController extends Controller
{
    /**
     * Thêm item vào cart của user
     * POST /api/cart-details
     * body: { product_detail_id, quantity, price, note? }
     */
    public function store(Request $request)
    {
        $payload = $request->all();

        try {
            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $validated = $request->validate([
                'product_detail_id' => 'required|exists:product_details,id',
                'quantity'          => 'required|integer|min:1',
                'price'             => 'required|numeric|min:0',
                'note'              => 'sometimes|nullable|string',
            ]);

            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id],
                ['Total_price' => 0]
            );

            // Nếu đã có product_detail trong cart thì cộng dồn số lượng
            $existing = CartDetail::where('cart_id', $cart->id)
                ->where('product_detail_id', $validated['product_detail_id'])
                ->first();

            if ($existing) {
                $existing->quantity = $existing->quantity + $validated['quantity'];
                $existing->subtotal = $existing->quantity * $existing->price;
                if (array_key_exists('note', $validated)) {
                    $existing->note = $validated['note'];
                }
                $existing->save();
                $detail = $existing;
            } else {
                $detail = CartDetail::create([
                    'cart_id' => $cart->id,
                    'product_detail_id' => $validated['product_detail_id'],
                    'quantity' => $validated['quantity'],
                    'price' => $validated['price'],
                    'subtotal' => $validated['quantity'] * $validated['price'],
                    'note' => $validated['note'] ?? null,
                ]);
            }

            // cập nhật tổng
            $total = CartDetail::where('cart_id', $cart->id)->sum('subtotal');
            $cart->Total_price = $total;
            $cart->save();

            return response()->json([
                'cart' => $cart,
                'detail' => $detail
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('CartDetailController@store error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Cập nhật item trong cart
     * PUT /api/cart-details/{id}
     * body: { quantity?, note? }
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::guard('api')->user();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $detail = CartDetail::findOrFail($id);

            // ensure detail belongs to user's cart
            $cart = Cart::where('id', $detail->cart_id)->where('user_id', $user->id)->first();
            if (!$cart) return response()->json(['message' => 'Not found or unauthorized'], 404);

            $validated = $request->validate([
                'quantity' => 'sometimes|integer|min:0',
                'note' => 'sometimes|nullable|string',
            ]);

            if (array_key_exists('quantity', $validated)) {
                $detail->quantity = $validated['quantity'];
                $detail->subtotal = $detail->quantity * $detail->price;
            }
            if (array_key_exists('note', $validated)) {
                $detail->note = $validated['note'];
            }
            $detail->save();

            // recalc cart total
            $cart->Total_price = CartDetail::where('cart_id', $cart->id)->sum('subtotal');
            $cart->save();

            return response()->json($detail);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Item not found'], 404);
        } catch (\Throwable $e) {
            Log::error('CartDetailController@update error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Xóa item khỏi cart
     * DELETE /api/cart-details/{id}
     */
    public function destroy($id)
    {
        try {
            $user = Auth::guard('api')->user();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $detail = CartDetail::findOrFail($id);

            $cart = Cart::where('id', $detail->cart_id)->where('user_id', $user->id)->first();
            if (!$cart) return response()->json(['message' => 'Not found or unauthorized'], 404);

            $detail->delete();

            // recalc cart total
            $cart->Total_price = CartDetail::where('cart_id', $cart->id)->sum('subtotal');
            $cart->save();

            return response()->json(['message' => 'Deleted']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Item not found'], 404);
        } catch (\Throwable $e) {
            Log::error('CartDetailController@destroy error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }
}
