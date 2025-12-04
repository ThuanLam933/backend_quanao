<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product_detail extends Model
{
    protected $fillable = [
       'color_id',
       'size_id',
       'product_id',
       'price',
       'quantity',
       'status',
    ];

    // relation to color
    public function color()
    {
        return $this->belongsTo(Color::class);
    }

    // relation to size
    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    // relation to the parent product
    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    // ⭐⭐ relation to images (MISSING → đã thêm)
    public function images()
    {
        return $this->hasMany(\App\Models\ImageProduct::class, 'product_detail_id');
    }
}
