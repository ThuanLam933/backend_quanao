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
}
