<?php

namespace App\Http\Controllers;

use App\Models\Product_detail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductDetailController extends Controller
{
    /**
     * Lấy danh sách product details (public)
     */
    public function index()
    {
        try {
            $details = Product_detail::with(['color', 'size'])
                ->orderBy('id', 'desc')
                ->get(['id', 'product_id', 'color_id', 'size_id', 'price', 'quantity', 'status', 'created_at', 'updated_at']);

            return response()->json($details);
        } catch (\Throwable $e) {
            Log::error('ProductDetail index error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Lấy chi tiết một product detail theo id (public)
     */
    public function show($id)
    {
        try {
            $detail = Product_detail::with(['color', 'size'])->findOrFail($id);
            return response()->json($detail);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Product detail không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('ProductDetail show error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Tạo mới product detail
     * Route: POST /api/product-details
     */
    public function store(Request $request)
    {
        Log::info('store ProductDetail called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));

        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'color_id'   => 'sometimes|nullable|exists:colors,id',
                'size_id'    => 'sometimes|nullable|exists:sizes,id',
                'price'      => 'sometimes|nullable|numeric|min:0',
                'quantity'   => 'sometimes|integer|min:0',
                'status'     => 'sometimes|boolean',
            ]);

            Log::info('Validation passed for ProductDetail.store', $validated);

            $dataToCreate = [
                'product_id' => $validated['product_id'],
                'color_id'   => $validated['color_id'] ?? null,
                'size_id'    => $validated['size_id'] ?? null,
                'price'      => array_key_exists('price', $validated) ? $validated['price'] : null,
                'quantity'   => $validated['quantity'] ?? 0,
                'status'     => array_key_exists('status', $validated) ? $validated['status'] : 1,
            ];

            Log::debug('Data to be inserted into product_details table', $dataToCreate);

            $detail = Product_detail::create($dataToCreate);

            Log::info('ProductDetail created with ID: ' . $detail->id, ['id' => $detail->id]);

            return response()->json($detail, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while creating product detail', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Create ProductDetail error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Cập nhật product detail
     * Route: PUT /api/product-details/{id}
     */
    public function update(Request $request, $id)
    {
        Log::info('update ProductDetail called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'id' => $id,
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $detail = Product_detail::findOrFail($id);

            $validated = $request->validate([
                'product_id' => 'sometimes|required|exists:products,id',
                'color_id'   => 'sometimes|nullable|exists:colors,id',
                'size_id'    => 'sometimes|nullable|exists:sizes,id',
                'price'      => 'sometimes|nullable|numeric|min:0',
                'quantity'   => 'sometimes|integer|min:0',
                'status'     => 'sometimes|boolean',
            ]);

            $updateData = [];
            foreach (['product_id', 'color_id', 'size_id', 'price', 'quantity', 'status'] as $f) {
                if (array_key_exists($f, $validated)) {
                    $updateData[$f] = $validated[$f];
                }
            }

            if (!empty($updateData)) {
                $detail->update($updateData);
            }

            Log::info('ProductDetail updated', ['id' => $detail->id]);

            return response()->json($detail);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while updating product detail', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Product detail không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Update ProductDetail error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Xóa product detail
     * Route: DELETE /api/product-details/{id}
     */
    public function destroy($id)
    {
        Log::info('destroy ProductDetail called', ['id' => $id]);

        try {
            $detail = Product_detail::findOrFail($id);
            $detail->delete();
            Log::info('ProductDetail deleted', ['id' => $id]);
            return response()->json(['message' => 'Đã xóa product detail thành công.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Product detail không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Delete ProductDetail error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Helper: lọc headers không cần log (tránh log token nhạy cảm)
     */
    protected function filterHeadersForLog(array $headers): array
    {
        $sensitive = [
            'authorization',
            'cookie',
            'x-xsrf-token',
            'x-csrf-token',
        ];

        $out = [];
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            if (in_array($lk, $sensitive)) {
                $out[$k] = '[REDACTED]';
            } else {
                $out[$k] = is_array($v) && count($v) === 1 ? $v[0] : $v;
            }
        }
        return $out;
    }
}
