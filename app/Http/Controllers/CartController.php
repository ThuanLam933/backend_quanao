<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Lấy danh sách carts (optionally filter by ?user_id=...)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cart::query();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        $carts = $query->with('user')->orderBy('id', 'desc')->get();

        return response()->json($carts);
    }

    /**
     * Lấy chi tiết 1 cart
     */
    public function show($id): JsonResponse
    {
        $cart = Cart::with('user')->find($id);

        if (! $cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        return response()->json($cart);
    }

    /**
     * Tạo cart mới.
     * Nếu user đã login -> gán user_id, nếu guest -> user_id = null
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Dùng Auth::id() thay vì auth()->id() để tránh cảnh báo static analyzer
            $userId = $request->input('user_id'); // null nếu guest

            // NOTE: dùng tên cột đúng theo migration của bạn.
            // Nếu migration dùng 'Total_price' (viết hoa T) thì giữ nguyên,
            // nếu migration dùng 'total_price' hãy sửa lại cho nhất quán.
            $cart = Cart::create([
                'Total_price' => $request->input('Total_price', 0),
                'user_id' => $userId,
            ]);

            return response()->json($cart, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating cart',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cập nhật cart (ví dụ cập nhật tổng tiền hoặc user_id)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $cart = Cart::find($id);
        if (! $cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $data = $request->only(['Total_price', 'user_id']);
        // Nếu Total_price được gửi là số hay chuỗi, có thể cast/validate trước khi save
        if (array_key_exists('Total_price', $data)) {
            $cart->Total_price = $data['Total_price'];
        }
        if (array_key_exists('user_id', $data)) {
            $cart->user_id = $data['user_id'];
        }

        $cart->save();

        return response()->json($cart);
    }

    /**
     * Xóa cart
     */
    public function destroy($id): JsonResponse
    {
        $cart = Cart::find($id);
        if (! $cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $cart->delete();

        return response()->json(['message' => 'Cart deleted']);
    }
}
