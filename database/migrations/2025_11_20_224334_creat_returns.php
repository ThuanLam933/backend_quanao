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
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            // liên kết đến order
            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');

            // liên kết đến product_details (màu/size cụ thể)
            $table->foreignId('product_detail_id')
                ->constrained('product_details')
                ->onDelete('cascade');

            // user request (nếu khách đã login)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->integer('quantity')->unsigned();
            $table->text('reason')->nullable();
            $table->string('requested_by')->nullable(); // tên hoặc email người yêu cầu (text tự do)
            $table->enum('status', ['pending','approved','rejected','refunded'])->default('pending');
            $table->text('admin_note')->nullable();

            // processed: đánh dấu đã cập nhật tồn kho khi approved (tránh xử lý 2 lần)
            $table->boolean('processed')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
