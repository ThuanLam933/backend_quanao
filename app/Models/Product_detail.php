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
    public function color()
    {
        return $this->belongsTo(Color::class);
    }
    public function size()
    {
        return $this->belongsTo(Size::class);
    }
}
