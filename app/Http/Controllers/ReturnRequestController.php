<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Models\Product_detail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReturnRequestController extends Controller
{
    
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 20);

        $query = ReturnRequest::with([
            'order',
            // dùng đúng tên quan hệ trong model: productDetail
            'productDetail.product',
            'productDetail.color',
            'productDetail.size',
            'user',
        ])->orderBy('created_at', 'desc');

 
        if ($s = $request->get('status')) {
            $query->where('status', $s);
        }
        if ($oid = $request->get('order_id')) {
            $query->where('order_id', $oid);
        }

        if ($request->get('all') == '1') {
            return response()->json($query->get());
        }

        $p = $query->paginate($perPage);
        return response()->json($p);
    }

 
    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id'          => ['required', 'integer', 'exists:orders,id'],
            'product_detail_id' => ['required', 'integer', 'exists:product_details,id'],
            'quantity'          => ['required', 'integer', 'min:1'],
            'reason'            => ['nullable', 'string'],
            'requested_by'      => ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            $ret = ReturnRequest::create(array_merge($data, [
           
                'user_id' => $request->user() ? $request->user()->id : null,
                'status'  => 'pending',
            ]));

            DB::commit();

            return response()->json(
   
                $ret->load(['order', 'productDetail', 'user']),
                201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Tạo phiếu thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $ret = ReturnRequest::with([
            'order',
            'productDetail.product',
            'productDetail.color',
            'productDetail.size',
            'user',
        ])->find($id);

        if (! $ret) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        return response()->json($ret);
    }

    public function update(Request $request, $id)
    {
        $ret = ReturnRequest::find($id);
        if (! $ret) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $data = $request->validate([
            'status'     => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'refunded'])],
            'admin_note' => ['nullable', 'string'],
        ]);

        DB::beginTransaction();
        try {
            $oldStatus = $ret->status;
            $newStatus = $data['status'] ?? $oldStatus;

            $ret->admin_note = $data['admin_note'] ?? $ret->admin_note;
            $ret->status     = $newStatus;

            if ($oldStatus !== 'approved' && $newStatus === 'approved' && ! $ret->processed) {
                $pd = Product_detail::lockForUpdate()->find($ret->product_detail_id);
                if ($pd) {
                    $pd->quantity = $pd->quantity + $ret->quantity;
                    $pd->save();
                    $ret->processed = true;
                }
            }

            $ret->save();

            DB::commit();
            return response()->json($ret->fresh());
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cập nhật thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $ret = ReturnRequest::find($id);
        if (! $ret) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        DB::beginTransaction();
        try {
            $ret->delete();
            DB::commit();
            return response()->json(['message' => 'Đã xóa']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Xóa thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
