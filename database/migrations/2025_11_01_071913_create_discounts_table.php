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
        Schema::create('discounts', function (Blueprint $table) {
    $table->id();

    // mã giảm giá
    $table->string('code')->unique();

    $table->string('name')->nullable();

    // % hoặc tiền
    $table->enum('type', ['percent', 'fixed']);

    // giá trị giảm
    $table->decimal('value', 12, 2);

    // điều kiện áp dụng
    $table->decimal('min_total', 12, 2)->nullable();

    // số lần dùng
    $table->integer('usage_limit')->nullable(); // null = không giới hạn
    $table->integer('usage_count')->default(0);

    // trạng thái
    $table->boolean('is_active')->default(true);

    // thời gian áp dụng
    $table->timestamp('start_at')->nullable();
    $table->timestamp('end_at')->nullable();

    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
