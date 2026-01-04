<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropForeign(['product_detail_id']);

        $table->foreign('product_detail_id')
              ->references('id')
              ->on('product_details')
              ->onDelete('restrict');
        });
    }

    
    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropForeign(['product_detail_id']);

        $table->foreign('product_detail_id')
              ->references('id')
              ->on('product_details')
              ->onDelete('cascade');
        });
    }
};
