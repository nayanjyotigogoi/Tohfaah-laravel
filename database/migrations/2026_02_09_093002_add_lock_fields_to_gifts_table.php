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
    Schema::table('gifts', function (Blueprint $table) {
        $table->boolean('lock_enabled')->default(false);
        $table->string('lock_question')->nullable();
        $table->string('lock_answer_hash')->nullable();
        $table->string('lock_hint')->nullable();
    });
}

public function down()
{
    Schema::table('gifts', function (Blueprint $table) {
        $table->dropColumn([
            'lock_enabled',
            'lock_question',
            'lock_answer_hash',
            'lock_hint',
        ]);
    });
}

};
