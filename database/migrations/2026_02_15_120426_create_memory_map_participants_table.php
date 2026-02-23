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
    Schema::create('memory_map_participants', function (Blueprint $table) {
        $table->id();

        $table->uuid('memory_map_id');
        $table->foreign('memory_map_id')
            ->references('id')
            ->on('memory_maps')
            ->cascadeOnDelete();

        $table->string('email');
        $table->foreignId('user_id')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->string('role')->default('participant');
        $table->string('status')->default('invited');
        $table->foreignId('invited_by')
            ->nullable()
            ->constrained('users')
            ->nullOnDelete();

        $table->timestamps();

        $table->unique(['memory_map_id', 'email']);
    });
}


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('memory_map_participants');
    }
};
