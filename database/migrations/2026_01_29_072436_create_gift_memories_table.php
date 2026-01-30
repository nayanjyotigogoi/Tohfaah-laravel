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
        Schema::create('gift_memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gift_id');
            $table->foreign('gift_id')->references('id')->on('gifts')->cascadeOnDelete();
            $table->text('image_url');
            $table->text('caption')->nullable();
            $table->integer('display_order')->default(0);
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
        Schema::dropIfExists('gift_memories');
    }
};
