<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartDetail extends Model
{
    protected $table = 'cart_details';

    protected $fillable = [
        'product_detail_id',
        'cart_id',
        'quantity',
        'price',
        'subtotal',
        'note',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function productDetail()
    {
        return $this->belongsTo(Product_detail::class, 'product_detail_id');
    }
}
