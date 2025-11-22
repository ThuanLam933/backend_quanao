<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnRequest extends Model
{
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'product_detail_id',
        'user_id',
        'quantity',
        'reason',
        'requested_by',
        'status',
        'admin_note',
        'processed',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'processed' => 'boolean',
    ];

    /* Relations */
    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class, 'order_id');
    }

    public function productDetail()
    {
        return $this->belongsTo(\App\Models\Product_detail::class, 'product_detail_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
