<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration 
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('gift_id')->nullable()->after('user_id');
            $table->foreign('gift_id')->references('id')->on('gifts')->nullOnDelete();

            $table->string('razorpay_payment_id')->nullable()->after('stripe_payment_id');
            $table->string('razorpay_order_id')->nullable()->after('razorpay_payment_id');
            $table->string('razorpay_signature')->nullable()->after('razorpay_order_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['gift_id']);
            $table->dropColumn(['gift_id', 'razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature']);
        });
    }
};
