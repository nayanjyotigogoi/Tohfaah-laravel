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
        Schema::create('free_gifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sender_id')->nullable();
            $table->string('gift_type');
            $table->string('recipient_name');
            $table->string('sender_name');
            $table->json('gift_data');
            $table->string('share_token')->unique();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('free_gifts');
    }
};
