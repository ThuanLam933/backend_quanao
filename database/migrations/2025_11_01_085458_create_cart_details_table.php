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
        Schema::create('cart_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_detail_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('cart_id')
                ->constrained()
                ->onDelete('cascade');

            $table->integer('quantity')->default(1);

            // Price and subtotal with precision
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);

            // note nullable (guest may not send a note)
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_details');
    }
};
