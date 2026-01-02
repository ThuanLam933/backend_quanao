<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('color_id')
                ->constrained('colors')
                ->cascadeOnDelete();

            $table->foreignId('size_id')
                ->constrained('sizes')
                ->cascadeOnDelete();

            // giá: nên dùng precision rõ ràng (ví dụ 12,0 cho VNĐ)
            $table->decimal('price', 12, 0)->default(0);

            $table->unsignedInteger('quantity')->default(0);

            // status dạng boolean cho đúng validate boolean
            $table->boolean('status')->default(true);

            // discount có thể không áp dụng
            $table->foreignId('product_discount_id')
                ->nullable()
                ->constrained('product_discounts')
                ->nullOnDelete();

            // ✅ chặn trùng biến thể theo combo
            $table->unique(['product_id', 'color_id', 'size_id'], 'uniq_product_color_size');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_details');
    }
};
