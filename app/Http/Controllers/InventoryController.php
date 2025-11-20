<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryLog;
use App\Models\Product_detail;
use App\Models\Receipt;
use App\Models\ReceiptDetail;
use Illuminate\Validation\ValidationException;
use Exception;

class InventoryController extends Controller
{
    /**
     * Constructor: apply auth middleware if you want.
     */
    public function __construct()
    {
        // uncomment if you use auth
        // $this->middleware('auth:api');
    }

    /**
     * List inventory logs with optional filters and pagination.
     *
     * Query params:
     * - product_detail_id
     * - type (receipt|sale|adjustment|revert_receipt|...)
     * - related_id
     * - date_from
     * - date_to
     * - per_page
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);

        $q = InventoryLog::query()->with(['productDetail', 'user']);

        if ($request->filled('product_detail_id')) {
            $q->where('product_detail_id', $request->query('product_detail_id'));
        }
        if ($request->filled('type')) {
            $q->where('type', $request->query('type'));
        }
        if ($request->filled('related_id')) {
            $q->where('related_id', $request->query('related_id'));
        }
        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $q->orderByDesc('created_at');

        return response()->json($q->paginate($perPage), 200);
    }

    /**
     * Manual adjustment endpoint.
     * Accepts:
     * {
     *   "product_detail_id": int,
     *   "change": int, // positive to increase stock, negative to decrease
     *   "note": string (optional)
     * }
     *
     * This will:
     * - lock product_detail row
     * - compute before/after
     * - update product_detail.quantity
     * - create InventoryLog with type 'adjustment'
     */
    public function adjust(Request $request)
    {
        $data = $request->validate([
            'product_detail_id' => 'required|exists:product_details,id',
            'change' => 'required|integer',
            'note' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $pd = Product_detail::lockForUpdate()->find($data['product_detail_id']);
            if (! $pd) {
                throw new Exception('Product detail not found');
            }

            $before = (int) ($pd->quantity ?? 0);
            $after = $before + intval($data['change']);
            if ($after < 0) {
                // prevent negative stock — business rule, you can change to allow negatives
                DB::rollBack();
                return response()->json(['message' => 'Adjustment would produce negative stock'], 422);
            }

            $pd->quantity = $after;
            $pd->save();

            $log = InventoryLog::create([
                'product_detail_id' => $pd->id,
                'change' => intval($data['change']),
                'quantity_before' => $before,
                'quantity_after' => $after,
                'type' => 'adjustment',
                'related_id' => null,
                'user_id' => $request->user() ? $request->user()->id : null,
                'note' => $data['note'] ?? null,
            ]);

            DB::commit();
            return response()->json($log, 201);
        } catch (ValidationException $ve) {
            DB::rollBack();
            return response()->json(['message' => 'Validation error', 'errors' => $ve->errors()], 422);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Inventory adjust failed: '.$e->getMessage());
            return response()->json(['message' => 'Adjustment failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Revert a receipt: subtract quantities added by a receipt.
     * Caution: business logic — only call if you want to undo a receipt.
     *
     * This will:
     * - find receipt and its details
     * - for each detail lock product_detail and subtract quantity
     * - create InventoryLog entries type 'revert_receipt'
     *
     * Returns 200 with logs array.
     */
    public function revertReceipt(Request $request, $receiptId)
    {
        DB::beginTransaction();
        try {
            $receipt = Receipt::with('details')->find($receiptId);
            if (! $receipt) {
                return response()->json(['message' => 'Receipt not found'], 404);
            }

            $resultLogs = [];
            foreach ($receipt->details as $d) {
                /** @var ReceiptDetail $d */
                $pd = Product_detail::lockForUpdate()->find($d->product_detail_id);
                if (! $pd) {
                    // if product detail missing, skip or throw
                    throw new Exception("Product detail {$d->product_detail_id} not found");
                }

                $before = (int) ($pd->quantity ?? 0);
                $after = $before - intval($d->quantity);
                if ($after < 0) {
                    // business decision: prevent negative by erroring out
                    DB::rollBack();
                    return response()->json(['message' => "Cannot revert, would produce negative stock for product_detail {$pd->id}"], 422);
                }

                $pd->quantity = $after;
                $pd->save();

                $log = InventoryLog::create([
                    'product_detail_id' => $pd->id,
                    'change' => - intval($d->quantity),
                    'quantity_before' => $before,
                    'quantity_after' => $after,
                    'type' => 'revert_receipt',
                    'related_id' => $receipt->id,
                    'user_id' => $request->user() ? $request->user()->id : null,
                    'note' => "Revert receipt #{$receipt->id}",
                ]);

                $resultLogs[] = $log;
            }

            DB::commit();
            return response()->json(['message' => 'Receipt reverted', 'logs' => $resultLogs], 200);
        } catch (Exception $e) {
            DB::rollBack();
            \Log::error('Revert receipt failed: '.$e->getMessage());
            return response()->json(['message' => 'Revert failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Optional: create a log entry without changing product_detail quantity.
     * Useful for importing historical logs or notes.
     */
    public function createLogOnly(Request $request)
    {
        $data = $request->validate([
            'product_detail_id' => 'required|exists:product_details,id',
            'change' => 'required|integer',
            'quantity_before' => 'required|integer',
            'quantity_after' => 'required|integer',
            'type' => 'required|string',
            'related_id' => 'nullable|integer',
            'note' => 'nullable|string',
        ]);

        $log = InventoryLog::create([
            'product_detail_id' => $data['product_detail_id'],
            'change' => $data['change'],
            'quantity_before' => $data['quantity_before'],
            'quantity_after' => $data['quantity_after'],
            'type' => $data['type'],
            'related_id' => $data['related_id'] ?? null,
            'user_id' => $request->user() ? $request->user()->id : null,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json($log, 201);
    }
}
