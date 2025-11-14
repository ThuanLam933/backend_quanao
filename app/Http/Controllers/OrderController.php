<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * Lấy danh sách đơn hàng (admin).
     */
    public function getAll(Request $request)
{
    try {
        // Lấy user từ JWT
        $user = $request->user() ?? auth('api')->user() ?? \Tymon\JWTAuth\Facades\JWTAuth::user();

        // Nếu chưa đăng nhập → unauthorized
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Nếu không phải admin → forbidden
        if (($user->role ?? '') !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Lấy toàn bộ đơn hàng
        $orders = Order::with('user', 'discount')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($orders, 200);

    } catch (\Throwable $e) {
        \Log::error('getAll Orders error: ' . $e->getMessage());
        return response()->json(['message' => 'Server error'], 500);
    }
}

    public function index(Request $request)
    {
        // Simple admin check - thay bằng middleware 'is_admin' nếu bạn có
        $user = $request->user();
        if (!$user || (($user->role ?? '') !== 'admin' && ($user->is_admin ?? false) !== true && ($user->isAdmin ?? false) !== true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $perPage = intval($request->query('per_page', 20));
        $q = Order::query()->with('user', 'discount')->orderBy('created_at', 'desc');

        // optional filters
        if ($request->filled('status')) {
            $q->where('status', $request->query('status'));
        }
        if ($request->filled('payment_method')) {
            $q->where('payment_method', $request->query('payment_method'));
        }

        $data = $q->paginate($perPage);

        return response()->json($data);
    }

    /**
     * Lấy chi tiết đơn hàng (owner hoặc admin).
     */
    public function show(Request $request, $id)
    {
        $order = Order::with('user', 'discount')->find($id);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $user = $request->user();

        // nếu không phải admin và không phải owner -> forbidden
        $isAdmin = $user && ((($user->role ?? '') === 'admin') || ($user->is_admin ?? false) === true || ($user->isAdmin ?? false) === true);
        if (!$isAdmin && (! $user || $order->user_id !== $user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($order);
    }

    /**
     * Tạo đơn hàng mới.
     *
     * Expected payload example (frontend):
     * {
     *   customer: { name, email, phone, address },
     *   items: [{ product_id, quantity, unit_price }, ...],
     *   payment: { method: 'cod'|'card', card: {...} },
     *   totals: { subtotal, shipping, total }
     * }
     */
    public function store(Request $request)
    {
        $rules = [
            'customer' => 'required|array',
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email|max:255',
            'customer.phone' => 'required|string|max:50',
            'customer.address' => 'required|string|max:1000',

            'items' => 'sometimes|array',
            'items.*.product_id' => 'required_with:items|integer',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',

            'payment' => 'nullable|array',
            'payment.method' => ['nullable', Rule::in(['cod', 'card', 'Banking', 'Cash'])],

            'totals' => 'nullable|array',
            'totals.subtotal' => 'nullable|numeric|min:0',
            'totals.shipping' => 'nullable|numeric|min:0',
            'totals.total' => 'nullable|numeric|min:0',
        ];

        $validator = \Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $payload = $request->all();

        DB::beginTransaction();
        try {
            $user = $request->user();

            // Determine total price: prefer totals.total from client, else compute from items if present
            $totalPrice = null;
            if (!empty($payload['totals']['total'])) {
                $totalPrice = (float) $payload['totals']['total'];
            } elseif (!empty($payload['items']) && is_array($payload['items'])) {
                $sum = 0;
                foreach ($payload['items'] as $it) {
                    $unit = isset($it['unit_price']) ? (float) $it['unit_price'] : 0;
                    $qty = isset($it['quantity']) ? (int) $it['quantity'] : 1;
                    $sum += $unit * $qty;
                }
                $shipping = $payload['totals']['shipping'] ?? 0;
                $totalPrice = $sum + (float)$shipping;
            } else {
                $totalPrice = (float) ($payload['totals']['subtotal'] ?? 0);
            }

            // create order
            $order = new Order();
            if ($user) $order->user_id = $user->id;
            // if discount_id present and valid, you can set it, here we try to use provided discount_id
            if (!empty($payload['discount_id'])) $order->discount_id = $payload['discount_id'];

            $order->order_code = (string) Str::uuid();
            $order->name = $payload['customer']['name'];
            $order->email = $payload['customer']['email'];
            $order->phone = $payload['customer']['phone'];
            $order->address = $payload['customer']['address'];
            // Nếu DB không cho NULL, lưu chuỗi rỗng thay vì null
            $order->note = $payload['note'] ?? '';

            $order->total_price = $totalPrice;
            // map payment method: translate 'cod' -> 'Cash' (migration uses 'Cash'|'Banking')
            $pm = $payload['payment']['method'] ?? null;
            if ($pm === 'cod') $order->payment_method = 'Cash';
            elseif ($pm === 'card' || $pm === 'Banking') $order->payment_method = 'Banking';
            else $order->payment_method = $payload['payment']['method'] ?? 'Banking';

            $order->status_stock = 1;
            $order->status = 'pending';

            $order->save();

            // TODO: If you have order items table/model, persist items here.
            // Example (if you have OrderItem model):
            // foreach ($payload['items'] as $it) {
            //     OrderItem::create([
            //         'order_id' => $order->id,
            //         'product_id' => $it['product_id'],
            //         'quantity' => $it['quantity'],
            //         'unit_price' => $it['unit_price'] ?? null,
            //     ]);
            // }

            DB::commit();

            // return created order (you can include items if you saved them)
            return response()->json([
                'message' => 'Order created',
                'order' => $order,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Create order error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error when creating order'], 500);
        }
    }

    /**
     * Cập nhật đơn hàng (admin) - ví dụ: update status, payment_method, note, total_price
     */
    public function update(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        $user = $request->user();
        if (!$user || ((($user->role ?? '') !== 'admin') && ($user->is_admin ?? false) !== true && ($user->isAdmin ?? false) !== true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rules = [
            'status' => ['nullable', Rule::in(['pending','confirmed','shipping','returned','completed','cancelled'])],
            'payment_method' => ['nullable', Rule::in(['Cash','Banking'])],
            'total_price' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
        ];
        $v = \Validator::make($request->all(), $rules);
        if ($v->fails()) return response()->json(['message' => 'Validation failed', 'errors' => $v->errors()], 422);

        try {
            $order->fill($v->validated());
            $order->save();
            return response()->json(['message' => 'Order updated', 'order' => $order]);
        } catch (\Throwable $e) {
            Log::error('Update order error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }

    /**
     * Xoá đơn hàng (admin)
     */
    public function destroy(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        $user = $request->user();
        if (!$user || ((($user->role ?? '') !== 'admin') && ($user->is_admin ?? false) !== true && ($user->isAdmin ?? false) !== true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $order->delete();
            return response()->json(['message' => 'Order deleted']);
        } catch (\Throwable $e) {
            Log::error('Delete order error: ' . $e->getMessage());
            return response()->json(['message' => 'Server error'], 500);
        }
    }
}
