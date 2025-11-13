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
        Schema::table('orders', function (Blueprint $table) {
           
         $table->dropForeign(['discount_id']);

            // Đổi cột thành nullable
            $table->unsignedBigInteger('discount_id')->nullable()->change();

            // Thêm lại foreign key - nên dùng SET NULL
            $table->foreign('discount_id')
                ->references('id')
                ->on('discounts')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
        $table->dropForeign(['discount_id']);

            // Đổi lại về NOT NULL
            $table->unsignedBigInteger('discount_id')->nullable(false)->change();

            // Thêm lại ràng buộc cũ
            $table->foreign('discount_id')
                ->references('id')
                ->on('discounts')
                ->onDelete('cascade');
        });
    }
};
