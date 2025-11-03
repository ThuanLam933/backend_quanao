<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
     protected $fillable = [
       'name',
       'min_total',
       'usage_limit',
       'usage_count',
       'status',
       'start_date',
       'end_date'
    ];
    public function order()
    {
        return $this->hasMany(Order::class);
    }
}
