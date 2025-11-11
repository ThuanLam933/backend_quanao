<?php

namespace App\Http\Controllers;

use App\Models\ImageProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ImageProductController extends Controller
{
    /**
     * List images (optionally filter by product_detail_id)
     */
    public function index(Request $request)
    {
        $q = ImageProduct::query();

        if ($request->has('product_detail_id')) {
            $q->where('product_detail_id', $request->input('product_detail_id'));
        }

        // optional pagination
        $perPage = intval($request->input('per_page', 20));
        $list = $q->orderBy('sort_order', 'asc')->paginate($perPage);

        // map to include full url attribute
        $list->getCollection()->transform(function ($item) {
            $item->full_url = $item->url; // accessor from model
            return $item;
        });

        return response()->json($list);
    }

    /**
     * Store new image (upload file or accept external URL)
     *
     * Accepted payload:
     * - image (file) OR url_image (string absolute url)
     * - product_detail_id (required)
     * - sort_order (optional)
     * - description (optional)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_detail_id' => ['required', 'integer', 'exists:product_details,id'],
            // either file upload or url string
            'image' => ['nullable', 'file', 'image', 'max:5120'], // max 5MB
            'url_image' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        // ensure at least one of image or url_image
        if (!$request->hasFile('image') && empty($validated['url_image'])) {
            return response()->json(['message' => 'Vui lòng cung cấp file ảnh hoặc url_image'], 422);
        }

        $path = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            // store in public disk under uploads/images
            $path = $file->store('uploads/images', 'public'); // returns path like uploads/images/xxx.jpg
        } else {
            // if external URL provided, store raw URL in DB
            $path = $validated['url_image'];
        }

        $image = ImageProduct::create([
            'product_detail_id' => $validated['product_detail_id'],
            'url_image' => $path,
            'sort_order' => $validated['sort_order'] ?? '',
            'description' => $validated['description'] ?? '',
        ]);

        // add full url in response
        $image->full_url = $image->url;

        return response()->json(['success' => true, 'image' => $image], 201);
    }

    /**
     * Show single image
     */
    public function show($id)
    {
        $img = ImageProduct::findOrFail($id);
        $img->full_url = $img->url;
        return response()->json($img);
    }

    /**
     * Update metadata or replace file
     *
     * - To replace file, send 'image' file. Old file will be removed (if stored on public disk).
     * - You can also update sort_order or description.
     */
    public function update(Request $request, $id)
    {
        $img = ImageProduct::findOrFail($id);

        $validated = $request->validate([
            'image' => ['nullable', 'file', 'image', 'max:5120'],
            'url_image' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        // Replace file if new uploaded
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $newPath = $file->store('uploads/images', 'public');

            // delete old file if it was stored in public disk (and not absolute URL)
            if ($img->url_image && !preg_match('/^https?:\\/\\//', $img->url_image)) {
                if (Storage::disk('public')->exists($img->url_image)) {
                    Storage::disk('public')->delete($img->url_image);
                }
            }

            $img->url_image = $newPath;
        } elseif ($request->filled('url_image')) {
            // set external url (no file store)
            $img->url_image = $validated['url_image'];
        }

        if ($request->filled('sort_order')) {
            $img->sort_order = $validated['sort_order'];
        }

        if ($request->filled('description')) {
            $img->description = $validated['description'];
        }

        $img->save();
        $img->full_url = $img->url;

        return response()->json(['success' => true, 'image' => $img]);
    }

    /**
     * Delete image record and delete file from storage (if managed on public disk)
     */
    public function destroy($id)
    {
        $img = ImageProduct::findOrFail($id);

        // delete physical file if present and not external
        if ($img->url_image && !preg_match('/^https?:\\/\\//', $img->url_image)) {
            if (Storage::disk('public')->exists($img->url_image)) {
                Storage::disk('public')->delete($img->url_image);
            }
        }

        $img->delete();

        return response()->json(['success' => true]);
    }
}
