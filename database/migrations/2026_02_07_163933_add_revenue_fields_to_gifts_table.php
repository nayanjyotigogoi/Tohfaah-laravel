<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('gifts', function (Blueprint $table) {

            // Revenue Control
            $table->string('payment_status')
                ->default('pending')
                ->after('status')
                ->index();

            $table->decimal('amount', 10, 2)
                ->nullable()
                ->after('payment_status');

            $table->string('coupon_code')
                ->nullable()
                ->after('amount')
                ->index();

            $table->boolean('coupon_applied')
                ->default(false)
                ->after('coupon_code');

            // Publishing lifecycle
            $table->timestamp('published_at')
                ->nullable()
                ->after('updated_at');

            $table->timestamp('expires_at')
                ->nullable()
                ->after('published_at');

        });
    }

    public function down()
    {
        Schema::table('gifts', function (Blueprint $table) {

            $table->dropColumn([
                'payment_status',
                'amount',
                'coupon_code',
                'coupon_applied',
                'published_at',
                'expires_at',
            ]);

        });
    }
};
