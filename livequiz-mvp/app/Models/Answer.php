<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Answer extends Model
{
    protected $fillable = ['question_id', 'text', 'is_correct', 'position'];

    protected $casts = [
        'is_correct' => 'boolean',
        'position' => 'integer',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function participantAnswers(): HasMany
    {
        return $this->hasMany(ParticipantAnswer::class);
    }
}
