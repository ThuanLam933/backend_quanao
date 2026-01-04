<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProductDiscount extends Model
{
    protected $table = 'product_discounts';

    protected $fillable = [
        'type',      
        'value',     
        'end_at',     
        'is_active',  
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_at'  => 'datetime',
        'end_at'    => 'datetime',
    ];

    
    public function productDetails()
    {
        return $this->hasMany(Product_detail::class, 'product_discount_id');
    }

  
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        if ($this->start_at && $now->lt($this->start_at)) {
            return false;
        }

        if ($this->end_at && $now->gt($this->end_at)) {
            return false;
        }

        return true;
    }


    public function applyDiscount($price): float
    {

        if ($this->type === 'percent') {
            return max(0, round($price * (1 - $this->value / 100)));
        }


        if ($this->type === 'fixed') {
            return max(0, $price - $this->value);
        }

        return (float) $price;
    }
}
