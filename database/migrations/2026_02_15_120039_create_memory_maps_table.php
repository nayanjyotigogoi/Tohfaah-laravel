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
    Schema::create('memory_maps', function (Blueprint $table) {
        $table->uuid('id')->primary();

        $table->foreignId('owner_id')
            ->constrained('users')
            ->cascadeOnDelete();

        $table->string('title');
        $table->text('description')->nullable();

        // Lifecycle
        $table->string('status')->default('draft');
        $table->string('payment_status')->default('unpaid');
        $table->decimal('amount', 10, 2)->nullable();

        // Access
        $table->uuid('share_token')->nullable()->unique();
        $table->integer('max_participants')->default(10);

        // Password Lock
        $table->boolean('has_password')->default(false);
        $table->string('password_hash')->nullable();
        $table->string('password_hint')->nullable();

        $table->timestamp('published_at')->nullable();

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
        Schema::dropIfExists('memory_maps');
    }
};
