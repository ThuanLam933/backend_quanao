<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use App\Models\Product_detail; // dùng để lock & trừ tồn
use App\Models\OrderItem;       // nếu bạn có model OrderItem
use App\Models\InventoryLog;    // audit log
use App\Models\Product;         // update product status
use Exception;

class OrderController extends Controller
{
    /**
     * Lấy danh sách đơn hàng (admin).
     */
    public function getAll(Request $request)
    {
        try {
            $user = $request->user() ?? auth('api')->user() ?? \Tymon\JWTAuth\Facades\JWTAuth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if (($user->role ?? '') !== 'admin') {
                return response()->json(['message' => 'Forbidden'], 403);
            }

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
        $user = $request->user();
        if (!$user || (($user->role ?? '') !== 'admin' && ($user->is_admin ?? false) !== true && ($user->isAdmin ?? false) !== true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $perPage = intval($request->query('per_page', 20));
        $q = Order::query()->with('user', 'discount')->orderBy('created_at', 'desc');

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

        $isAdmin = $user && (((($user->role ?? '') === 'admin') || ($user->is_admin ?? false) === true || ($user->isAdmin ?? false) === true));
        if (!$isAdmin && (! $user || $order->user_id !== $user->id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($order);
    }

    /**
     * Tạo đơn hàng mới.
     *
     * Backend sẽ trừ tồn ngay khi order được tạo (trong transaction).
     * Nếu thiếu tồn => trả 422.
     */
    public function store(Request $request)
    {
        $rules = [
            'customer' => 'required|array',
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email|max:255',
            'customer.phone' => 'required|string|max:50',
            'customer.address' => 'required|string|max:1000',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.product_detail_id' => 'nullable|integer',
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
        $items = $payload['items'] ?? [];

        DB::beginTransaction();
        try {
            $user = $request->user();

            // compute totalPrice
            $totalPrice = null;
            if (!empty($payload['totals']['total'])) {
                $totalPrice = (float) $payload['totals']['total'];
            } elseif (!empty($items) && is_array($items)) {
                $sum = 0;
                foreach ($items as $it) {
                    $unit = isset($it['unit_price']) ? (float) $it['unit_price'] : 0;
                    $qty = isset($it['quantity']) ? (int) $it['quantity'] : 1;
                    $sum += $unit * $qty;
                }
                $shipping = $payload['totals']['shipping'] ?? 0;
                $totalPrice = $sum + (float)$shipping;
            } else {
                $totalPrice = (float) ($payload['totals']['subtotal'] ?? 0);
            }

            // Pre-check availability: lock each product_detail (by id if provided,
            // otherwise pick a product_detail for product_id that has sufficient qty)
            $lockedPDs = []; // store Product_detail instances keyed by item index
            foreach ($items as $idx => $it) {
                $qtyReq = (int) ($it['quantity'] ?? 0);
                $pd = null;

                if (!empty($it['product_detail_id'])) {
                    // lock by product_detail_id
                    $pd = Product_detail::lockForUpdate()->find($it['product_detail_id']);
                    if (! $pd) {
                        DB::rollBack();
                        return response()->json(['message' => 'Product detail not found', 'product_detail_id' => $it['product_detail_id']], 422);
                    }
                } elseif (!empty($it['product_id'])) {
                    // attempt to find one product_detail of that product with enough quantity
                    $pd = Product_detail::where('product_id', $it['product_id'])
                        ->where('quantity', '>=', $qtyReq)
                        ->lockForUpdate()
                        ->first();

                    // fallback: if none has >= qty, pick first detail (we will then report insufficient)
                    if (! $pd) {
                        $pd = Product_detail::where('product_id', $it['product_id'])->lockForUpdate()->first();
                        if (! $pd) {
                            DB::rollBack();
                            return response()->json(['message' => 'No product detail found for product', 'product_id' => $it['product_id']], 422);
                        }
                    }
                } else {
                    DB::rollBack();
                    return response()->json(['message' => 'Item must include product_detail_id or product_id', 'index' => $idx], 422);
                }

                $available = (int)($pd->quantity ?? 0);
                if ($available < $qtyReq) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Insufficient stock',
                        'product_detail_id' => $pd->id,
                        'available' => $available,
                        'requested' => $qtyReq,
                    ], 422);
                }

                $lockedPDs[$idx] = $pd;
            }

            // Create order
            $order = new Order();
            if ($user) $order->user_id = $user->id;
            if (!empty($payload['discount_id'])) $order->discount_id = $payload['discount_id'];

            $order->order_code = (string) Str::uuid();
            $order->name = $payload['customer']['name'];
            $order->email = $payload['customer']['email'];
            $order->phone = $payload['customer']['phone'];
            $order->address = $payload['customer']['address'];
            $order->note = $payload['note'] ?? '';
            $order->total_price = $totalPrice;

            $pm = $payload['payment']['method'] ?? null;
            if ($pm === 'cod') $order->payment_method = 'Cash';
            elseif ($pm === 'card' || $pm === 'Banking') $order->payment_method = 'Banking';
            else $order->payment_method = $payload['payment']['method'] ?? 'Banking';

            $order->status_stock = 1;
            $order->status = 'pending';

            $order->save();

            // Create OrderItem, decrement stock, create InventoryLog
            foreach ($items as $idx => $it) {
                $qty = (int) ($it['quantity'] ?? 0);
                $unitPrice = isset($it['unit_price']) ? (float) $it['unit_price'] : null;

                $pd = $lockedPDs[$idx] ?? null;
                if (! $pd) {
                    if (!empty($it['product_detail_id'])) {
                        $pd = Product_detail::lockForUpdate()->find($it['product_detail_id']);
                    } elseif (!empty($it['product_id'])) {
                        $pd = Product_detail::where('product_id', $it['product_id'])->lockForUpdate()->first();
                    }
                }
                if (! $pd) {
                    DB::rollBack();
                    return response()->json(['message' => 'Product detail lost during order creation'], 500);
                }

                if ($unitPrice === null) {
                    $unitPrice = (float) ($pd->price ?? 0);
                }

                if (class_exists(OrderItem::class)) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_detail_id' => $pd->id,
                        'product_id' => $pd->product_id ?? null,
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'subtotal' => $qty * $unitPrice,
                    ]);
                } else {
                    \Log::warning('OrderItem model not found - skipping item persist. Create OrderItem model to store items.');
                }

                $beforeQty = (int) ($pd->quantity ?? 0);
                $pd->quantity = max(0, $beforeQty - $qty);

                if (array_key_exists('status', $pd->getAttributes()) || property_exists($pd, 'status')) {
                    $pd->status = ($pd->quantity > 0) ? 1 : 0;
                }
                $pd->save();

                if (class_exists(InventoryLog::class)) {
                    InventoryLog::create([
                        'product_detail_id' => $pd->id,
                        'change' => -$qty,
                        'quantity_before' => $beforeQty,
                        'quantity_after' => (int)$pd->quantity,
                        'type' => 'order',
                        'related_id' => $order->id,
                        'user_id' => $user ? $user->id : null,
                        'note' => "Giảm kho do đơn hàng #{$order->id}",
                    ]);
                }

                // refresh parent product status
                try {
                    if (!empty($pd->product_id)) {
                        $this->refreshProductStockStatus($pd->product_id);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Could not update parent product status: '.$e->getMessage());
                }
            }

            DB::commit();

            $order->load('user');

            return response()->json([
                'message' => 'Order created',
                'order' => $order,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Create order error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString(), 'payload' => $payload]);
            return response()->json(['message' => 'Server error when creating order', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cập nhật đơn hàng (admin)
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

    /**
     * Helper: refresh product.status based on its product_details.
     * Sets product.status = 1 if any product_detail.quantity > 0, else 0.
     */
    protected function refreshProductStockStatus($productId)
    {
        if (empty($productId)) return;

        $hasStock = Product_detail::where('product_id', $productId)->where('quantity', '>', 0)->exists();

        if (class_exists(Product::class)) {
            $prod = Product::find($productId);
            if ($prod && (array_key_exists('status', $prod->getAttributes()) || property_exists($prod, 'status'))) {
                $prod->status = $hasStock ? 1 : 0;
                $prod->save();
            }
        }
    }
}
