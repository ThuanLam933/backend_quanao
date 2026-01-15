<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_otps', function (Blueprint $table) {
            $table->string('email')->unique()->after('id');
            $table->unsignedBigInteger('user_id')->nullable()->after('email');
            $table->string('otp')->after('user_id');
            $table->timestamp('expires_at')->after('otp');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('email_otps', function (Blueprint $table) {
            // drop foreign key trước
            $table->dropForeign(['user_id']);

            // drop index unique trước rồi mới drop cột (để tránh lỗi tuỳ DB)
            $table->dropUnique(['email']);

            $table->dropColumn(['email', 'user_id', 'otp', 'expires_at']);
        });
    }
};
