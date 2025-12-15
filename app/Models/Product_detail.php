<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductDiscount;



class Product_detail extends Model
{
    protected $fillable = [
       'color_id',
       'size_id',
       'product_id',
       'price',
       'quantity',
       'status',
       'product_discount_id',
    ];

    // relation to color
    public function color()
    {
        return $this->belongsTo(Color::class);
    }
    public function discount()
{
    return $this->belongsTo(ProductDiscount::class, 'product_discount_id');
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
    

/**
 * Giá cuối sau giảm
 */
public function getFinalPriceAttribute()
{
    if ($this->discount && $this->discount->isValid()) {
        return $this->discount->applyDiscount($this->price);
    }

    return $this->price;
}
protected $appends = [
        'final_price',
        'has_discount',
    ];

    public function getHasDiscountAttribute()
    {
        return $this->discount && $this->discount->isValid();
    }

}
