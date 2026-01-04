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

    public function color()
    {
        return $this->belongsTo(Color::class);
    }
    public function discount()
{
    return $this->belongsTo(ProductDiscount::class, 'product_discount_id');
}

    public function size()
    {
        return $this->belongsTo(Size::class);
    }

    public function product()
    {
        return $this->belongsTo(\App\Models\Product::class, 'product_id');
    }

    public function images()
    {
        return $this->hasMany(\App\Models\ImageProduct::class, 'product_detail_id');
    }
    


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
