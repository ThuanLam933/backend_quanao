<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['discount_id']);
            $table->unsignedBigInteger('discount_id')->nullable()->change();
            $table->foreign('discount_id')
                ->references('id')
                ->on('discounts')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['discount_id']);
            $table->unsignedBigInteger('discount_id')->nullable(false)->change();
            $table->foreign('discount_id')
                ->references('id')
                ->on('discounts')
                ->onDelete('cascade');
        });
    }
};
