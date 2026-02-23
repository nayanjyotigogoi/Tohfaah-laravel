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
   public function up(): void
{
    Schema::create('map_memories', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->uuid('memory_map_id');
        $table->foreign('memory_map_id')
            ->references('id')
            ->on('memory_maps')
            ->cascadeOnDelete();

        $table->foreignId('user_id')
            ->constrained('users')
            ->cascadeOnDelete();

        $table->string('title');
        $table->string('badge');
        $table->text('message')->nullable();
        $table->string('photo_url')->nullable();

        $table->decimal('latitude', 10, 7);
        $table->decimal('longitude', 10, 7);

        $table->date('memory_date')->nullable();

        $table->integer('display_order')->index();

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
        Schema::dropIfExists('map_memories');
    }
};
