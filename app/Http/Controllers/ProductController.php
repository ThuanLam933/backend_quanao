<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Lấy danh sách product (public)
     */
    public function products()
    {
        try {
            $products = Product::select('id', 'name', 'slug', 'description', 'status', 'image_url', 'categories_id')
                ->get()
                ->map(function ($p) {
                    $p->image_url = $p->image_url ? asset('storage/' . $p->image_url) : null;
                    return $p;
                });

            return response()->json($products);
        } catch (\Throwable $e) {
            // Log chi tiết để dễ debug
            Log::error('Products error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Thêm product mới
     * Route: POST /api/addProduct
     */
    public function addProduct(Request $request)
    {
        // Log bắt đầu request (không log thông tin nhạy cảm)
        Log::info('addProduct called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        // Log headers (nếu cần debug client) - comment/uncomment tuỳ ý
        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));

        // Log payload (thô) - lưu ý không log file binary
        $payload = $request->except(['image']); // bỏ image file khỏi log
        Log::debug('Request payload (except image file)', $payload);

        try {
            // Validation
            $validated = $request->validate([
                'name'          => 'required|string|max:255',
                'slug'          => 'nullable|string|max:255',
                'description'   => 'required|string',
                'status'        => 'required|boolean',
                'categories_id' => 'required|exists:categories,id',

                // upload file OR image_url string
                'image'         => 'sometimes|file|image|max:5120',         // max 5MB
                'image_url'     => 'sometimes|nullable|string|max:2048',
            ]);

            Log::info('Validation passed for addProduct', $validated);

            // Xử lý upload file nếu có
            if ($request->hasFile('image')) {
                try {
                    $path = $request->file('image')->store('products', 'public'); // lưu vào storage/app/public/products
                    // lưu path vào validated để dùng khi tạo product
                    $validated['image_url'] = $path;

                    Log::info('Image uploaded', ['storage_path' => $path]);
                } catch (\Throwable $e) {
                    // Nếu lỗi khi lưu file, log chi tiết và ném tiếp để vào catch tổng
                    Log::error('Image store failed: ' . $e->getMessage());
                    Log::error($e->getTraceAsString());
                    throw $e;
                }
            } else {
                Log::debug('No image file in request; using image_url if provided', [
                    'image_url_present' => isset($validated['image_url'])
                ]);
            }

            // Chuẩn bị dữ liệu để tạo product (log trước khi create)
            $dataToCreate = [
    'name'          => $validated['name'],
    'slug'          => $validated['slug'] ?? null,
    'description'   => $validated['description'],
    'status'        => $validated['status'],
    'categories_id' => $validated['categories_id'],
    // nếu không có image_url, lưu chuỗi rỗng
    'image_url'     => $validated['image_url'] ?? '',
];

            Log::debug('Data to be inserted into products table', $dataToCreate);

            // Tạo product
            $product = Product::create($dataToCreate);

            // Chuẩn hoá image_url trước khi trả về response
            $product->image_url = $product->image_url ? asset('storage/' . $product->image_url) : null;

            Log::info('Product created with ID: ' . $product->id, ['product_id' => $product->id]);

            return response()->json([
                'message' => 'Thêm sản phẩm thành công!',
                'product' => $product,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed while creating product', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            // Log chi tiết để debug: message + trace + request payload (an toàn)
            Log::error('AddProduct error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);

            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Helper: lọc headers không cần log (tránh log token nhạy cảm)
     */
    protected function filterHeadersForLog(array $headers): array
    {
        // Các header nhạy cảm cần loại bỏ
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
                // flatten values
                $out[$k] = is_array($v) && count($v) === 1 ? $v[0] : $v;
            }
        }
        return $out;
    }
}
