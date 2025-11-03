<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categories;
use Illuminate\Support\Facades\Log;

class CategoriesController extends Controller
{
    public function index()
    {
        try {
            $categories = Categories::select('id', 'slug', 'name')->get();
            return response()->json($categories);
        } catch (\Throwable $e) {
            Log::error('Categories index error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'slug' => 'required|string|max:255|unique:categories,slug',
                'name' => 'required|string|max:255',
            ]);

            Log::info('Creating category: ' . $data['name']);

            $category = Categories::create([
                'slug' => $data['slug'],
                'name' => $data['name'],
            ]);

            Log::info('Category created with ID: ' . $category->id);

            return response()->json([
                'message' => 'Tạo category thành công!',
                'category' => $category,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Category store error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function show($id)
    {
        try {
            $category = Categories::find($id);
            if (! $category) {
                return response()->json(['message' => 'Category không tồn tại'], 404);
            }
            return response()->json($category);
        } catch (\Throwable $e) {
            Log::error('Category show error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $data = $request->validate([
                'slug' => 'required|string|max:255|unique:categories,slug,' . $id,
                'name' => 'required|string|max:255',
            ]);

            $category = Categories::find($id);
            if (! $category) {
                return response()->json(['message' => 'Category không tồn tại'], 404);
            }

            $category->update($data);

            Log::info('Category updated ID: ' . $category->id);

            return response()->json([
                'message' => 'Cập nhật category thành công!',
                'category' => $category,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Category update error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $category = Categories::find($id);
            if (! $category) {
                return response()->json(['message' => 'Category không tồn tại'], 404);
            }

            $category->delete();

            Log::info('Category deleted ID: ' . $id);

            return response()->json(['message' => 'Xóa category thành công!']);
        } catch (\Throwable $e) {
            Log::error('Category delete error: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }
}
