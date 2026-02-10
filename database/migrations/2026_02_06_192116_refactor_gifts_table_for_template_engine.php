<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('gifts', function (Blueprint $table) {

            // Add flexible config column
            $table->json('config')->nullable()->after('secret_hint');

            // Drop old template-specific fields
            $table->dropColumn([
                'recipient_nickname',
                'sender_nickname',

                'message_title',
                'message_body',
                'message_style',

                'has_love_letter',
                'love_letter_content',
                'love_letter_style',

                'has_memories',
                'has_gallery',
                'has_map',
                'has_proposal',

                'sender_location',
                'recipient_location',
                'distance_message',

                'proposal_question',
                'proposed_datetime',
                'proposed_location',
                'proposed_activity',
                'proposal_response',

                'intro_animation',
                'transition_style',
                'background_music',
            ]);
        });
    }

    public function down()
    {
        Schema::table('gifts', function (Blueprint $table) {

            $table->dropColumn('config');

            // If rollback ever needed (optional minimal restore)
            $table->string('recipient_nickname')->nullable();
            $table->string('sender_nickname')->nullable();
        });
    }
};
