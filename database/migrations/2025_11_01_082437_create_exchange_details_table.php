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
        Schema::create('exchange_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('product_detail_id')
                ->constrained()
                ->onDelete('cascade');
            $table->integer('quantity');
            $table->integer('product_old_id');
            $table->integer('product_new_id');
            $table->text('reason');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_details');
    }
};
