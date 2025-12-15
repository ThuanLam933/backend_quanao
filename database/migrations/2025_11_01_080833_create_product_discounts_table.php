<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_discounts', function (Blueprint $table) {
    $table->id();

    // $table->foreignId('product_id')
    //     ->constrained()
    //     ->cascadeOnDelete();

    // kiểu giảm giá
    $table->enum('type', ['percent', 'fixed']);

    // giá trị giảm (10 = 10% | 50000 = 50.000đ)
    $table->decimal('value', 12, 2);

    // thời gian hiệu lực
    $table->timestamp('start_at')->nullable();
    $table->timestamp('end_at')->nullable();

    // bật / tắt
    $table->boolean('is_active')->default(true);

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_discounts');
    }
};
