<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ProductDiscount extends Model
{
    protected $table = 'product_discounts';

    protected $fillable = [
        'type',       // percent | fixed
        'value',      // số % hoặc số tiền
        'start_at',   // datetime | null
        'end_at',     // datetime | null
        'is_active',  // boolean
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'start_at'  => 'datetime',
        'end_at'    => 'datetime',
    ];

    /**
     * Quan hệ: 1 discount có nhiều product_detail
     * (GIỮ NGUYÊN Product_detail, KHÔNG đổi tên)
     */
    public function productDetails()
    {
        return $this->hasMany(Product_detail::class, 'product_discount_id');
    }

    /**
     * Kiểm tra discount còn hiệu lực hay không
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = Carbon::now();

        // Chưa tới ngày bắt đầu
        if ($this->start_at && $now->lt($this->start_at)) {
            return false;
        }

        // Đã quá ngày kết thúc
        if ($this->end_at && $now->gt($this->end_at)) {
            return false;
        }

        return true;
    }

    /**
     * Áp dụng discount để tính giá sau giảm
     */
    public function applyDiscount($price): float
    {
        // Giảm theo %
        if ($this->type === 'percent') {
            return max(0, round($price * (1 - $this->value / 100)));
        }

        // Giảm tiền cố định
        if ($this->type === 'fixed') {
            return max(0, $price - $this->value);
        }

        return (float) $price;
    }
}
