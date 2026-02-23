<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        /*
        |--------------------------------------------------------------------------
        | Fix memory_map_participants
        |--------------------------------------------------------------------------
        */

        Schema::table('memory_map_participants', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['invited_by']);
            $table->dropColumn(['user_id', 'invited_by']);
        });

        Schema::table('memory_map_participants', function (Blueprint $table) {
            $table->uuid('user_id')->nullable();
            $table->uuid('invited_by')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('invited_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | Fix map_memories
        |--------------------------------------------------------------------------
        */

        Schema::table('map_memories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('map_memories', function (Blueprint $table) {
            $table->uuid('user_id');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down()
    {
        // optional rollback — can leave empty if not needed
    }
};
