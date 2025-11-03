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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
             $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
             $table->foreignId('discount_id')
                ->constrained()
                ->onDelete('cascade');
            $table->uuid('order_code');
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('address');
            $table->text('note');
            $table->decimal('total_price');
            $table->tinyInteger('status_stock')->default('1');
            $table->enum('payment_method', ['Cash','Banking'])->default('Banking');            
            $table->enum('status', ['pending','confirmed', 'shipping','returned','completed','cancelled'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
