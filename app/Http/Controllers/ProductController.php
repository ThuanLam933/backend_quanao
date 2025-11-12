<?php

namespace App\Http\Controllers;

use GuzzleHttp\Promise\Create;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Product_detail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Lấy danh sách product (public)
     */
    public function products()
    {
        try {
            $products = Product::with(['details.color','details.size'])
                ->select('id','name','slug','description','status','image_url','categories_id')
                ->orderBy('id','desc')
                ->get()
                ->map(function($p) {
                    // chuẩn hoá image_url
                    $p->image_url = $p->image_url ? asset('storage/' . ltrim($p->image_url, '/')) : null;

                    // chuẩn hoá details (map url cho ảnh nếu details có ảnh riêng)
                    if ($p->relationLoaded('details')) {
                        $p->details = $p->details->map(function($d){
                            // nếu detail có đường dẫn ảnh, chuẩn hoá (tuỳ schema)
                            if (isset($d->image_url) && $d->image_url) {
                                $d->image_url = asset('storage/' . ltrim($d->image_url, '/'));
                            }
                            return $d;
                        })->toArray();
                    } else {
                        $p->details = [];
                    }

                    // thêm một helper: first_detail để frontend hiển thị nhanh
                    $first = $p->details[0] ?? null;
                    $p->first_detail = $first ? (object)[
                        'price' => $first['price'] ?? null,
                        'color' => $first['color'] ?? null,
                        'size'  => $first['size'] ?? null,
                        'quantity' => $first['quantity'] ?? null,
                    ] : null;

                    return $p;
                });

            return response()->json($products);
        } catch (\Throwable $e) {
            Log::error('Products error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }


    /**
     * Thêm product mới
     * Route: POST /api/products
     */
    public function addProduct(Request $request)
    {
        Log::info('addProduct called', [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        Log::debug('Request headers', $this->filterHeadersForLog($request->headers->all()));
        $payload = $request->except(['image', 'images']);
        Log::debug('Request payload (except image file)', $payload);

        try {
            // Basic validation for product fields (files omitted)
            $validated = $request->validate([
                'name'          => 'required|string|max:255',
                'slug'          => 'nullable|string|max:255',
                'description'   => 'required|string',
                'status'        => 'required|boolean',
                'categories_id' => 'required|exists:categories,id',
                // file rules
                'image'         => 'sometimes|file|image|max:5120',
                'images'        => 'sometimes|array',
                'images.*'      => 'file|image|max:5120',
                'image_url'     => 'sometimes|nullable|string|max:2048',
                // note: details may be sent as JSON string in multipart requests -> we'll parse below
            ]);

            Log::info('Validation passed for addProduct', $validated);

            // Ensure slug exists and is unique
            if (empty($validated['slug'])) {
                $base = Str::slug($validated['name']);
                $slug = $base ?: 'p-' . time();
                $i = 1;
                while (Product::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $validated['slug'] = $slug;
            } else {
                // if provided, ensure uniqueness (append suffix if exists)
                $base = Str::slug($validated['slug']);
                $slug = $base ?: Str::slug($validated['name']);
                $i = 1;
                while (Product::where('slug', $slug)->exists()) {
                    $slug = $base . '-' . $i++;
                }
                $validated['slug'] = $slug;
            }

            // Handle images upload: prefer first uploaded file (images[] or image)
            if ($request->hasFile('images') && is_array($request->file('images'))) {
                $files = $request->file('images');
                if (count($files) > 0) {
                    $first = $files[0];
                    $path = $first->store('products', 'public');
                    $validated['image_url'] = $path;
                    Log::info('Images[] uploaded, first stored', ['path' => $path]);
                }
            } elseif ($request->hasFile('image')) {
                $path = $request->file('image')->store('products', 'public');
                $validated['image_url'] = $path;
                Log::info('Single image uploaded', ['path' => $path]);
            } else {
                Log::debug('No image file in request; using image_url if provided', [
                    'image_url_present' => isset($validated['image_url'])
                ]);
            }

            // Data to insert for product
            $dataToCreate = [
                'name'          => $validated['name'],
                'slug'          => $validated['slug'],
                'description'   => $validated['description'],
                'status'        => $validated['status'],
                'categories_id' => $validated['categories_id'],
                'image_url'     => $validated['image_url'] ?? '',
            ];

            // Begin transaction: create product and details atomically
            DB::beginTransaction();

            // create product
            $product = Product::create($dataToCreate);

            // --- Handle details input (supports JSON array from frontend or top-level single fields) ---
            $detailsInput = $request->input('details', []);

            // If details was sent as JSON string (common with multipart/form-data), decode it
            if (is_string($detailsInput) && $detailsInput !== '') {
                $decoded = json_decode($detailsInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $detailsInput = $decoded;
                } else {
                    // invalid JSON -> log and set to empty
                    Log::warning('Invalid JSON in details field', ['raw' => $detailsInput]);
                    $detailsInput = [];
                }
            }

            // If no details array provided, but top-level variant fields exist (backward compatibility), build single detail
            $topLevelDetail = [
                'color_id' => $request->input('color_id', null),
                'size_id' => $request->input('size_id', null),
                'price' => $request->has('price') ? $request->input('price') : null,
                'quantity' => $request->input('quantity', 0),
            ];
            $hasTopLevelDetail = $topLevelDetail['price'] !== null || $topLevelDetail['color_id'] !== null || $topLevelDetail['size_id'] !== null || ($topLevelDetail['quantity'] !== null && $topLevelDetail['quantity'] > 0);

            if (empty($detailsInput) && $hasTopLevelDetail) {
                $detailsInput[] = $topLevelDetail;
            }

            // Validate each detail entry minimally (numeric price, existing color/size optional)
            foreach ($detailsInput as $idx => $dRaw) {
                // normalize to array
                $d = is_array($dRaw) ? $dRaw : [];

                $v = Validator::make($d, [
                    'price' => 'nullable|numeric',
                    'color_id' => 'nullable|exists:colors,id',
                    'size_id'  => 'nullable|exists:sizes,id',
                    'quantity' => 'nullable|integer',
                    'image_url' => 'sometimes|nullable|string|max:2048',
                ]);

                if ($v->fails()) {
                    // rollback and return validation error for detail
                    DB::rollBack();
                    Log::warning('Detail validation failed', ['index' => $idx, 'errors' => $v->errors()->toArray()]);
                    return response()->json(['message' => 'Validation failed for product details', 'errors' => $v->errors()], 422);
                }

                $detailData = [
                    'price' => array_key_exists('price', $d) ? $d['price'] : null,
                    'color_id' => $d['color_id'] ?? null,
                    'size_id'  => $d['size_id'] ?? null,
                    'quantity' => $d['quantity'] ?? 0,
                    'image_url' => $d['image_url'] ?? null,
                ];

                // create via relation so product_id is set automatically
                if (method_exists($product, 'details')) {
                    $product->details()->create($detailData);
                } else {
                    $detailData['product_id'] = $product->id;
                    Product_detail::create($detailData);
                }
            }

            DB::commit();

            // reload relations and format image_url for return
            $product->load('details.color','details.size');
            $product->image_url = $product->image_url ? asset('storage/' . ltrim($product->image_url, '/')) : null;

            // Map details image_url normalization similar to products() method
            if ($product->relationLoaded('details') && $product->details) {
                $product->details = $product->details->map(function($d){
                    if (isset($d->image_url) && $d->image_url) {
                        $d->image_url = asset('storage/' . ltrim($d->image_url, '/'));
                    }
                    return $d;
                })->toArray();
            } else {
                $product->details = [];
            }

            Log::info('Product created with ID: ' . $product->id, ['product_id' => $product->id]);

            return response()->json([
                'message' => 'Thêm sản phẩm thành công!',
                'product' => $product,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // validation errors from initial product validation
            DB::rollBack();
            Log::warning('Validation failed while creating product', $e->errors());
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('AddProduct error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            Log::error('Last known payload', $payload);
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * Cập nhật product
     * Accepts: POST with _method=PUT + FormData OR real PUT JSON
     */
    public function update(Request $request, $id)
{
    Log::info('updateProduct called', ['id' => $id, 'path' => $request->path(), 'method' => $request->method()]);
    $payload = $request->except(['image', 'images']);
    Log::debug('Update payload (except files)', $payload);

    try {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'slug'          => 'nullable|string|max:255',
            'description'   => 'sometimes|required|string',
            'status'        => 'sometimes|required|boolean',
            'categories_id' => 'sometimes|required|exists:categories,id',
            'image'         => 'sometimes|file|image|max:5120',
            'images'        => 'sometimes|array',
            'images.*'      => 'file|image|max:5120',
            'image_url'     => 'sometimes|nullable|string|max:2048',
            // details is intentionally loose; we'll validate each item later
            'details'       => 'sometimes',
            'deleted_detail_ids' => 'sometimes|array',
            'deleted_detail_ids.*' => 'integer',
        ]);

        // Slug handling: ensure unique excluding current product
        if (isset($validated['slug']) && $validated['slug'] !== null && $validated['slug'] !== '') {
            $base = Str::slug($validated['slug']);
            $slug = $base ?: Str::slug($validated['name'] ?? $product->name);
            $i = 1;
            while (Product::where('slug', $slug)->where('id', '<>', $product->id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $validated['slug'] = $slug;
        } elseif (isset($validated['name']) && (!isset($validated['slug']) || $validated['slug'] === '')) {
            // generate slug from name if slug not provided
            $base = Str::slug($validated['name']);
            $slug = $base ?: 'p-' . time();
            $i = 1;
            while (Product::where('slug', $slug)->where('id', '<>', $product->id)->exists()) {
                $slug = $base . '-' . $i++;
            }
            $validated['slug'] = $slug;
        }

        // Handle uploaded images: set first to image_url
        if ($request->hasFile('images') && is_array($request->file('images'))) {
            $files = $request->file('images');
            if (count($files) > 0) {
                $first = $files[0];
                $path = $first->store('products', 'public');
                $validated['image_url'] = $path;
                Log::info('Images[] uploaded (update), first stored', ['path' => $path]);
            }
        } elseif ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_url'] = $path;
            Log::info('Single image uploaded (update)', ['path' => $path]);
        }

        DB::beginTransaction();

        // Update product fields
        $product->fill($validated);
        $product->save();

        // --- Handle details if provided ---
        $detailsInput = $request->input('details', null);

        if ($detailsInput !== null) {
            // If details is JSON string (multipart), decode it
            if (is_string($detailsInput) && $detailsInput !== '') {
                $decoded = json_decode($detailsInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $detailsInput = $decoded;
                } else {
                    Log::warning('Invalid JSON in details field (update)', ['raw' => $detailsInput]);
                    $detailsInput = [];
                }
            }

            // If details is not array, normalize to empty array
            if (!is_array($detailsInput)) {
                $detailsInput = [];
            }

            $processedIds = [];

            foreach ($detailsInput as $idx => $dRaw) {
                $d = is_array($dRaw) ? $dRaw : [];

                $v = Validator::make($d, [
                    'id' => 'sometimes|integer|exists:product_details,id',
                    'price' => 'nullable|numeric',
                    'color_id' => 'nullable|exists:colors,id',
                    'size_id'  => 'nullable|exists:sizes,id',
                    'quantity' => 'nullable|integer',
                    'image_url' => 'sometimes|nullable|string|max:2048',
                ]);

                if ($v->fails()) {
                    DB::rollBack();
                    Log::warning('Detail validation failed (update)', ['index' => $idx, 'errors' => $v->errors()->toArray()]);
                    return response()->json(['message' => 'Validation failed for product details', 'errors' => $v->errors()], 422);
                }

                $detailData = [
                    'price' => array_key_exists('price', $d) ? $d['price'] : null,
                    'color_id' => $d['color_id'] ?? null,
                    'size_id'  => $d['size_id'] ?? null,
                    'quantity' => $d['quantity'] ?? 0,
                    'image_url' => $d['image_url'] ?? null,
                ];

                // If id provided and belongs to this product => update
                if (!empty($d['id'])) {
                    $detail = $product->details()->where('id', $d['id'])->first();
                    if ($detail) {
                        $detail->update($detailData);
                        $processedIds[] = $detail->id;
                    } else {
                        // id provided but not found on this product: skip or create new (we skip)
                        Log::warning('Detail id provided but not found for this product (update)', ['detail_id' => $d['id'], 'product_id' => $product->id]);
                    }
                } else {
                    // create new detail
                    $new = $product->details()->create($detailData);
                    $processedIds[] = $new->id;
                }
            }

            // Handle explicit deletions: deleted_detail_ids[]
            $deleted = $request->input('deleted_detail_ids', []);
            if (is_array($deleted) && count($deleted) > 0) {
                $toDelete = $product->details()->whereIn('id', $deleted)->pluck('id')->toArray();
                if (count($toDelete) > 0) {
                    $product->details()->whereIn('id', $toDelete)->delete();
                }
            }

            // Optionally: if frontend wants a full replace (all details replaced by provided list),
            // they can send 'replace_details' = true. In that case, delete details not in processedIds.
            if ($request->boolean('replace_details') && count($processedIds) > 0) {
                $product->details()->whereNotIn('id', $processedIds)->delete();
            }
        }

        DB::commit();

        // reload relations and normalize urls
        $product->load('details.color','details.size');
        $product->image_url = $product->image_url ? asset('storage/' . ltrim($product->image_url, '/')) : null;

        if ($product->relationLoaded('details') && $product->details) {
            $product->details = $product->details->map(function($d){
                if (isset($d->image_url) && $d->image_url) {
                    $d->image_url = asset('storage/' . ltrim($d->image_url, '/'));
                }
                return $d;
            })->toArray();
        } else {
            $product->details = [];
        }

        Log::info('Product updated', ['id' => $product->id]);

        return response()->json(['message' => 'Cập nhật thành công', 'product' => $product]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        DB::rollBack();
        Log::warning('Validation failed while updating product', $e->errors());
        return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
    } catch (\Throwable $e) {
        DB::rollBack();
        Log::error('UpdateProduct error: ' . $e->getMessage());
        Log::error($e->getTraceAsString());
        Log::error('Last known payload', $payload);
        return response()->json(['message' => 'Lỗi server'], 500);
    }
}


    /**
     * Xoá product
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);
            // optionally: delete associated images from storage if needed
            if ($product->image_url) {
                try {
                    \Storage::disk('public')->delete($product->image_url);
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete product image from storage', ['msg' => $e->getMessage()]);
                }
            }
            $product->delete();
            Log::info('Product deleted', ['id' => $id]);
            return response()->json(['message' => 'Đã xóa'], 200);
        } catch (\Throwable $e) {
            Log::error('Delete product error: ' . $e->getMessage());
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
