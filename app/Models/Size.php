<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    protected $fillable = [
       'name'
    ];
    public function product_detail()
    {
        return $this->hasMany(Product_detail::class);
    }
}
