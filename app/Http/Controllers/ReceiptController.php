<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\ReceiptDetail;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Product_detail;

class ReceiptController extends Controller
{
    /**
     * List receipts (paginated).
     * Optional filters: supplier_id, date_from, date_to
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);
        $q = Receipt::with(['supplier', 'user'])->orderBy('import_date', 'desc');

        if ($request->filled('supplier_id')) {
            $q->where('suppliers_id', $request->get('supplier_id'));
        }
        if ($request->filled('date_from')) {
            $q->whereDate('import_date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate('import_date', '<=', $request->get('date_to'));
        }

        return $q->paginate($perPage);
    }

    /**
     * Show one receipt with details
     */
    public function show(Receipt $receipt)
    {
        $receipt->load(['supplier', 'details.productDetail']);
        return $receipt;
    }

    /**
     * Create receipt with details.
     *
     * Expected payload:
     * {
     *   suppliers_id: int,
     *   note: string,
     *   import_date: "YYYY-MM-DD", (optional)
     *   items: [
     *     { product_detail_id: int, quantity: int, price: decimal }
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'suppliers_id' => 'required|exists:suppliers,id',
            'note' => 'nullable|string',
            'import_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.product_detail_id' => 'required|exists:product_details,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        $userId = $request->user() ? $request->user()->id : null;

        DB::beginTransaction();
        try {
            $total = 0;
            foreach ($data['items'] as $it) {
                $subtotal = (float)$it['quantity'] * (float)$it['price'];
                $total += $subtotal;
            }

            $receipt = Receipt::create([
                'user_id' => $userId,
                'suppliers_id' => $data['suppliers_id'],
                'note' => $data['note'] ?? '',
                'total_price' => $total,
                'import_date' => $data['import_date'] ?? now()->toDateString(),
            ]);

            foreach ($data['items'] as $it) {
                $subtotal = (float)$it['quantity'] * (float)$it['price'];

                $detail = ReceiptDetail::create([
                    'product_detail_id' => $it['product_detail_id'],
                    'receipt_id' => $receipt->id,
                    'quantity' => $it['quantity'],
                    'price' => $it['price'],
                    'subtotal' => $subtotal,
                ]);

                // Update product_detail stock: increase quantity
                $pd = Product_detail::lockForUpdate()->find($it['product_detail_id']);
                if ($pd) {
                    $pd->quantity = ($pd->quantity ?? 0) + intval($it['quantity']);
                    $pd->save();
                }
            }

            DB::commit();

            $receipt->load(['supplier', 'details.productDetail']);
            return response()->json($receipt, 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Receipt create failed: '.$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return response()->json(['message' => 'Create failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete receipt (and rollback stock?).
     * NOTE: deleting a receipt will NOT reduce stock by default.
     * If you want automatic stock rollback on delete, enable the commented block.
     */
    public function destroy(Receipt $receipt)
    {
        DB::beginTransaction();
        try {
            // Optional: rollback stock when deleting a receipt
            // foreach ($receipt->details as $d) {
            //     $pd = ProductDetail::lockForUpdate()->find($d->product_detail_id);
            //     if ($pd) {
            //         $pd->quantity = max(0, ($pd->quantity ?? 0) - $d->quantity);
            //         $pd->save();
            //     }
            // }

            $receipt->details()->delete();
            $receipt->delete();

            DB::commit();
            return response()->json(['message' => 'Deleted']);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Receipt delete failed: '.$e->getMessage());
            return response()->json(['message' => 'Delete failed'], 500);
        }
    }
}
