<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantAnswer extends Model
{
    protected $fillable = [
        'participant_id',
        'question_id',
        'answer_id',
        'selected_answer_ids',
        'question_text',
        'answer_text',
        'is_correct',
        'score',
        'response_ms',
        'answered_at',
    ];

    protected $casts = [
        'selected_answer_ids' => 'array',
        'is_correct' => 'boolean',
        'score' => 'integer',
        'response_ms' => 'integer',
        'answered_at' => 'datetime',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }
}
