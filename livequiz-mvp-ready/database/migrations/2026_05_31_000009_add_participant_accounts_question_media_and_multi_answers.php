<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->json('image_urls')->nullable()->after('text');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->foreignId('participant_user_id')->nullable()->after('game_session_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('participant_answers', function (Blueprint $table) {
            $table->json('selected_answer_ids')->nullable()->after('answer_id');
        });
    }

    public function down(): void
    {
        Schema::table('participant_answers', function (Blueprint $table) {
            $table->dropColumn('selected_answer_ids');
        });

        Schema::table('participants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('participant_user_id');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('image_urls');
        });
    }
};
