<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
       'Total_price',
       'user_id'
    ];

    // relation to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // relation to cart details (items)
    public function cartDetails()
    {
        return $this->hasMany(CartDetail::class, 'cart_id');
    }
}
