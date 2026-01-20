<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeDetail extends Model
{
    protected $fillable = [
        'exchange_id',
        'product_detail_id',
        'quantity',
        'product_old_id',
        'product_new_id',
        'reason',
        'created_at',
        'updated_at',
    ];

    public function exchange()
    {
        return $this->belongsTo(Exchange::class);
    }

    public function productDetail()
    {
        return $this->belongsTo(Product_detail::class, 'product_detail_id');
    }

    
    public function productOldDetail()
    {
        return $this->belongsTo(Product_detail::class, 'product_old_id');
    }

    
    public function productNewDetail()
    {
        return $this->belongsTo(Product_detail::class, 'product_new_id');
    }
}
