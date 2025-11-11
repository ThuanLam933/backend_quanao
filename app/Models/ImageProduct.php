<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

class ImageProduct extends Model
{
    protected $table = 'image_products';

    // Có thể gán hàng loạt các field này
    protected $fillable = [
        'product_detail_id',
        'url_image',
        'sort_order',
        'description',
    ];

    // Casts (nếu cần) — giữ sort_order là string theo migration
    protected $casts = [
        //'sort_order' => 'integer',
    ];

    /**
     * Relation: image belongs to a product detail
     *
     * Lưu ý: tên model ProductDetail phải khớp với file app/Models/ProductDetail.php
     */
    public function productDetail()
    {
        return $this->belongsTo(\App\Models\Product_detail::class, 'product_detail_id');
    }

    /**
     * Accessor: full URL to the image (using disk 'public').
     * If url_image already an absolute URL, return it unchanged.
     *
     * Usage:
     *   $imageProduct->url        // trả về url đầy đủ hoặc null
     *   $imageProduct->full_url  // alias cho url
     */
    // public function getUrlAttribute()
    // {
    //     if (! $this->url_image) {
    //         return null;
    //     }

    //     // If stored as absolute URL, return it as-is
    //     if (preg_match('/^https?:\\/\\//i', $this->url_image)) {
    //         return $this->url_image;
    //     }

    //     // Use a typed local variable so static analyzers/IDE hiểu method url() tồn tại
    //     try {
    //         /** @var FilesystemAdapter $disk */
    //         $disk = Storage::disk('public');

    //         // FilesystemAdapter::url() sẽ trả về đường dẫn có thể truy cập (ví dụ: /storage/...)
    //         return $disk->url($this->url_image);
    //     } catch (\Throwable $e) {
    //         // Nếu có lỗi (ví dụ disk không tồn tại), fallback trả về giá trị raw đã lưu
    //         return $this->url_image;
    //     }
    // }

    /**
     * Alias cho clarity ở frontend: $model->full_url
     */
    public function getFullUrlAttribute()
    {
        return $this->getUrlAttribute();
    }
    public function getUrlAttribute()
{
    if (! $this->url_image) {
        return null;
    }

    // Nếu đã là URL tuyệt đối (bắt đầu bằng http)
    if (preg_match('/^https?:\\/\\//i', $this->url_image)) {
        return $this->url_image;
    }

    // Nếu là đường dẫn tương đối => thêm domain
    $baseUrl =  'http://127.0.0.1:8000';

    // Trả về full URL tuyệt đối
    return $baseUrl . '/storage/' . ltrim($this->url_image, '/');
}
}
