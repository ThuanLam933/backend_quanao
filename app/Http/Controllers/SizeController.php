<?php

namespace App\Http\Controllers;

use App\Models\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SizeController extends Controller
{
    /**
     * Lấy danh sách size (public)
     */
    public function index()
    {
        try {
            $sizes = Size::orderBy('name')->get(['id', 'name', 'created_at', 'updated_at']);
            return response()->json($sizes);
        } catch (\Throwable $e) {
            Log::error('Sizes index error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Lấy chi tiết một size theo id (public)
     */
    public function show($id)
    {
        try {
            $size = Size::findOrFail($id);
            return response()->json($size);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Size không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Size show error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Tạo mới một size.
     * Route: POST /api/sizes (thường protected bằng auth:api)
     */
    public function store(Request $request)
    {
        // Log request summary (không log dữ liệu nhạy cảm)
        Log::info('store Size called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            Log::info('Validation passed for Size.store', $validated);

            $size = Size::create([
                'name' => $validated['name'],
            ]);

            Log::info('Size created with ID: ' . $size->id, ['size_id' => $size->id]);

            return response()->json($size, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while creating size', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Create Size error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Cập nhật size.
     * Route: PUT /api/sizes/{id}
     */
    public function update(Request $request, $id)
    {
        // Log request
        Log::info('update Size called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'id' => $id,
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $size = Size::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $size->update(['name' => $validated['name']]);

            Log::info('Size updated', ['size_id' => $size->id]);

            return response()->json($size);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while updating size', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Size không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Update Size error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Xóa size.
     * Route: DELETE /api/sizes/{id}
     */
    public function destroy($id)
    {
        Log::info('destroy Size called', ['id' => $id]);

        try {
            $size = Size::findOrFail($id);
            $size->delete();
            Log::info('Size deleted', ['size_id' => $id]);
            return response()->json(['message' => 'Đã xóa size thành công.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Size không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Delete Size error: ' . $e->getMessage());
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
