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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // Total_price với precision và default để tránh lỗi tính toán
            $table->decimal('Total_price', 12, 2)->default(0);

            // user_id nullable — cho phép guest cart
            $table->unsignedBigInteger('user_id')->nullable();

            // timestamps
            $table->timestamps();

            // foreign key: nếu user bị xóa -> set NULL (giữ cart guest)
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
