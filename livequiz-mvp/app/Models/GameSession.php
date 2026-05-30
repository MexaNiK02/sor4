<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSession extends Model
{
    protected $fillable = [
        'quiz_id',
        'code',
        'status',
        'current_question_id',
        'current_phase',
        'current_question_started_at',
        'reveal_started_at',
        'reveal_ends_at',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'current_question_started_at' => 'datetime',
        'reveal_started_at' => 'datetime',
        'reveal_ends_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['waiting', 'active']);
    }

    public function refreshTimedState(): self
    {
        if ($this->status !== 'active' || ! $this->current_question_id) {
            return $this;
        }

        if ($this->current_phase === 'reveal') {
            if ($this->reveal_ends_at && now()->greaterThanOrEqualTo($this->reveal_ends_at)) {
                return $this->moveToNextQuestion();
            }

            return $this;
        }

        $question = $this->currentQuestion()->first();

        if (! $question || ! $this->current_question_started_at) {
            return $this;
        }

        $endsAt = $this->current_question_started_at->copy()->addSeconds($question->timer_seconds);

        if (now()->greaterThanOrEqualTo($endsAt)) {
            return $this->beginReveal();
        }

        return $this;
    }

    public function startQuestion(Question $question): self
    {
        $this->update([
            'status' => 'active',
            'current_question_id' => $question->id,
            'current_phase' => 'question',
            'current_question_started_at' => now(),
            'reveal_started_at' => null,
            'reveal_ends_at' => null,
            'started_at' => $this->started_at ?? now(),
        ]);

        return $this->refresh();
    }

    public function beginReveal(): self
    {
        $this->update([
            'current_phase' => 'reveal',
            'reveal_started_at' => now(),
            'reveal_ends_at' => now()->addSeconds(5),
        ]);

        return $this->refresh();
    }

    public function moveToNextQuestion(): self
    {
        $questions = $this->quiz->questions()->get();
        $currentIndex = $questions->search(fn ($question) => $question->id === $this->current_question_id);
        $nextQuestion = $questions->get($currentIndex + 1);

        if (! $nextQuestion) {
            $this->update([
                'status' => 'finished',
                'current_phase' => 'finished',
                'finished_at' => now(),
            ]);

            return $this->refresh();
        }

        return $this->startQuestion($nextQuestion);
    }

    public function questionSecondsRemaining(): int
    {
        if ($this->status !== 'active' || $this->current_phase !== 'question' || ! $this->currentQuestion || ! $this->current_question_started_at) {
            return 0;
        }

        return $this->secondsUntil($this->current_question_started_at->copy()->addSeconds($this->currentQuestion->timer_seconds));
    }

    public function revealSecondsRemaining(): int
    {
        if ($this->status !== 'active' || $this->current_phase !== 'reveal' || ! $this->reveal_ends_at) {
            return 0;
        }

        return $this->secondsUntil($this->reveal_ends_at);
    }

    private function secondsUntil(CarbonInterface $moment): int
    {
        return max(0, now()->diffInSeconds($moment, false));
    }
}
