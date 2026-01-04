<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Cart::query();

        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        $carts = $query->with('user')->orderBy('id', 'desc')->get();

        return response()->json($carts);
    }

    public function show($id): JsonResponse
    {
        $cart = Cart::with('user')->find($id);

        if (! $cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        return response()->json($cart);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id'); 
        
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

    
    public function update(Request $request, $id): JsonResponse
    {
        $cart = Cart::find($id);
        if (! $cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $data = $request->only(['Total_price', 'user_id']);
        if (array_key_exists('Total_price', $data)) {
            $cart->Total_price = $data['Total_price'];
        }
        if (array_key_exists('user_id', $data)) {
            $cart->user_id = $data['user_id'];
        }

        $cart->save();

        return response()->json($cart);
    }

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
