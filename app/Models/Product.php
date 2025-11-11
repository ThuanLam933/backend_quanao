<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
       'name',
       'slug',
       'description',
       'status',
       'image_url',
       'categories_id'
    ];
    public function categories()
    {
        return $this->belongsTo(Categories::class);
    }
    public function details()
    {
        return $this->hasMany(Product_detail::class, 'product_id', 'id');
    }
}
