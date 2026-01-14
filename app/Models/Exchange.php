<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'note',
        'status',
        'create_exchange',
        'created_at',
        'updated_at',
    ];

    public function exchangeDetails()
    {
        return $this->hasMany(ExchangeDetail::class);
    }
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
