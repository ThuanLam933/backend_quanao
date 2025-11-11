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

    // === NEW === relation to the parent product
    public function product()
    {
        // assumes you have App\Models\Product model; adjust namespace if different
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }
}
