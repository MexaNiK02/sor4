<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->string('current_phase')->default('question')->after('current_question_id');
            $table->timestamp('reveal_started_at')->nullable()->after('current_question_started_at');
            $table->timestamp('reveal_ends_at')->nullable()->after('reveal_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn(['current_phase', 'reveal_started_at', 'reveal_ends_at']);
        });
    }
};
