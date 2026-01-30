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
        Schema::create('gifts', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('sender_id')->nullable();
            $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();

            $table->string('template_type');
            $table->string('status')->default('draft');
            $table->string('share_token')->nullable()->unique();

            $table->string('recipient_name');
            $table->string('recipient_nickname')->nullable();
            $table->string('sender_name');
            $table->string('sender_nickname')->nullable();

            $table->boolean('has_secret_question')->default(false);
            $table->text('secret_question')->nullable();
            $table->string('secret_answer_hash')->nullable();
            $table->string('secret_hint')->nullable();

            $table->string('message_title')->nullable();
            $table->text('message_body')->nullable();
            $table->string('message_style')->nullable();

            $table->boolean('has_love_letter')->default(false);
            $table->text('love_letter_content')->nullable();
            $table->string('love_letter_style')->nullable();

            $table->boolean('has_memories')->default(false);
            $table->boolean('has_gallery')->default(false);
            $table->boolean('has_map')->default(false);
            $table->boolean('has_proposal')->default(false);

            $table->json('sender_location')->nullable();
            $table->json('recipient_location')->nullable();
            $table->text('distance_message')->nullable();

            $table->text('proposal_question')->nullable();
            $table->timestamp('proposed_datetime')->nullable();
            $table->string('proposed_location')->nullable();
            $table->string('proposed_activity')->nullable();
            $table->string('proposal_response')->nullable();

            $table->string('intro_animation')->nullable();
            $table->string('transition_style')->nullable();
            $table->string('background_music')->nullable();

            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('first_viewed_at')->nullable();

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
        Schema::dropIfExists('gifts');
    }
};
