<?php

namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ColorController extends Controller
{
    /**
     * Lấy danh sách màu (public)
     */
    public function index()
    {
        try {
            $colors = Color::orderBy('name')->get(['id', 'name', 'created_at', 'updated_at']);
            return response()->json($colors);
        } catch (\Throwable $e) {
            Log::error('Colors index error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Lấy chi tiết một màu theo id (public)
     */
    public function show($id)
    {
        try {
            $color = Color::findOrFail($id);
            return response()->json($color);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Color không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Color show error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Tạo mới một màu.
     * Route: POST /api/colors (thường protected bằng auth:api)
     */
    public function store(Request $request)
    {
        // Log request summary (không log dữ liệu nhạy cảm)
        Log::info('store Color called', [
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

            Log::info('Validation passed for Color.store', $validated);

            $color = Color::create([
                'name' => $validated['name'],
            ]);

            Log::info('Color created with ID: ' . $color->id, ['color_id' => $color->id]);

            return response()->json($color, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while creating color', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Create Color error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Cập nhật màu.
     * Route: PUT /api/colors/{id}
     */
    public function update(Request $request, $id)
    {
        // Log request
        Log::info('update Color called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'id' => $id,
        ]);
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->all();
        Log::debug('Request payload', $payload);

        try {
            $color = Color::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $color->update(['name' => $validated['name']]);

            Log::info('Color updated', ['color_id' => $color->id]);

            return response()->json($color);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while updating color', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Color không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Update Color error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Xóa màu.
     * Route: DELETE /api/colors/{id}
     */
    public function destroy($id)
    {
        Log::info('destroy Color called', ['id' => $id]);

        try {
            $color = Color::findOrFail($id);
            $color->delete();
            Log::info('Color deleted', ['color_id' => $id]);
            return response()->json(['message' => 'Đã xóa màu thành công.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Color không tồn tại'], 404);
        } catch (\Throwable $e) {
            Log::error('Delete Color error: ' . $e->getMessage());
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
