<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Participant;
use App\Services\LiveQuizBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function status(Request $request, Participant $participant): JsonResponse
    {
        $this->authorizeParticipant($request, $participant);
        $participant->update(['last_seen_at' => now()]);

        $session = $participant->session()
            ->with('quiz.questions.answers', 'currentQuestion.answers')
            ->firstOrFail()
            ->refreshTimedState()
            ->load('quiz.questions.answers', 'currentQuestion.answers');

        $participantAnswer = $session->current_question_id
            ? $participant->answers()->where('question_id', $session->current_question_id)->first()
            : null;
        $isReveal = $session->status === 'active' && $session->current_phase === 'reveal';

        return response()->json([
            'data' => [
                'participant' => $participant->refresh(),
                'session' => [
                    'id' => $session->id,
                    'code' => $session->code,
                    'status' => $session->status,
                    'current_phase' => $session->current_phase,
                    'current_question_started_at' => optional($session->current_question_started_at)->toIso8601String(),
                    'reveal_ends_at' => optional($session->reveal_ends_at)->toIso8601String(),
                    'question_seconds_remaining' => $session->questionSecondsRemaining(),
                    'reveal_seconds_remaining' => $session->revealSecondsRemaining(),
                    'quiz' => [
                        'id' => $session->quiz->id,
                        'title' => $session->quiz->title,
                        'question_count' => $session->quiz->questions->count(),
                    ],
                    'current_question' => $session->currentQuestion ? [
                        'id' => $session->currentQuestion->id,
                        'text' => $session->currentQuestion->text,
                        'type' => $session->currentQuestion->type,
                        'image_urls' => $session->currentQuestion->image_urls ?? [],
                        'timer_seconds' => $session->currentQuestion->timer_seconds,
                        'position' => $session->currentQuestion->position,
                        'answers' => $session->currentQuestion->answers->map(fn ($answer) => [
                            'id' => $answer->id,
                            'text' => $answer->text,
                            'is_correct' => $isReveal ? $answer->is_correct : null,
                        ]),
                    ] : null,
                    'answered_current_question' => (bool) $participantAnswer,
                    'current_answer_result' => $isReveal ? [
                        'answered' => (bool) $participantAnswer,
                        'is_correct' => (bool) optional($participantAnswer)->is_correct,
                        'selected_answer_id' => optional($participantAnswer)->answer_id,
                        'selected_answer_ids' => optional($participantAnswer)->selected_answer_ids ?? [],
                        'score' => (int) optional($participantAnswer)->score,
                    ] : null,
                ],
            ],
        ]);
    }

    public function answer(Request $request, Participant $participant, LiveQuizBroadcaster $broadcaster): JsonResponse
    {
        $this->authorizeParticipant($request, $participant);

        $payload = $request->validate([
            'answer_id' => ['nullable', 'integer', 'exists:answers,id'],
            'answer_ids' => ['nullable', 'array', 'min:1'],
            'answer_ids.*' => ['integer', 'exists:answers,id'],
        ]);

        $session = $participant->session()
            ->with('currentQuestion.answers')
            ->firstOrFail()
            ->refreshTimedState();

        abort_if($session->status !== 'active' || $session->current_phase !== 'question' || ! $session->currentQuestion, 422, 'Сейчас нет активного вопроса.');
        abort_if(
            $participant->answers()->where('question_id', $session->current_question_id)->exists(),
            422,
            'Ответ на этот вопрос уже отправлен.'
        );

        $selectedIds = collect($payload['answer_ids'] ?? [$payload['answer_id'] ?? null])
            ->filter()
            ->unique()
            ->values();

        abort_if($selectedIds->isEmpty(), 422, 'Выберите хотя бы один вариант ответа.');
        abort_if($session->currentQuestion->type === 'single_choice' && $selectedIds->count() !== 1, 422, 'Для этого вопроса можно выбрать только один ответ.');

        $answers = Answer::whereIn('id', $selectedIds)->get();
        abort_if($answers->count() !== $selectedIds->count(), 422, 'Некоторые ответы не найдены.');
        abort_if($answers->contains(fn (Answer $answer) => $answer->question_id !== $session->current_question_id), 422, 'Ответ не относится к текущему вопросу.');

        $correctIds = $session->currentQuestion->answers
            ->where('is_correct', true)
            ->pluck('id')
            ->sort()
            ->values();
        $normalizedSelectedIds = $selectedIds->sort()->values();
        $isCorrect = $correctIds->all() === $normalizedSelectedIds->all();

        $responseMs = now()->diffInMilliseconds($session->current_question_started_at);
        $maxMs = max(1, $session->currentQuestion->timer_seconds * 1000);
        $speedBonus = $isCorrect ? max(0, (int) round((1 - min($responseMs, $maxMs) / $maxMs) * 50)) : 0;
        $score = $isCorrect ? 100 + $speedBonus : 0;

        $participantAnswer = $participant->answers()->create([
            'question_id' => $session->current_question_id,
            'answer_id' => $selectedIds->count() === 1 ? $selectedIds->first() : null,
            'selected_answer_ids' => $selectedIds->all(),
            'question_text' => $session->currentQuestion->text,
            'answer_text' => $answers->sortBy('position')->pluck('text')->implode(', '),
            'is_correct' => $isCorrect,
            'score' => $score,
            'response_ms' => $responseMs,
            'answered_at' => now(),
        ]);

        $participant->increment('score', $score);
        $broadcaster->answerReceived($session->fresh(), $participant->id);

        return response()->json([
            'data' => $participantAnswer,
            'participant' => $participant->refresh(),
        ], 201);
    }

    public function result(Request $request, Participant $participant): JsonResponse
    {
        $this->authorizeParticipant($request, $participant);

        $session = $participant->session()->with('quiz.questions.answers')->firstOrFail()->refreshTimedState();
        $leaderboard = $this->leaderboardPayload($session);
        $participantRank = $leaderboard->firstWhere('id', $participant->id)['rank'] ?? 1;

        $answers = $participant->answers()
            ->with('question', 'answer')
            ->get();

        $mistakes = $answers
            ->where('is_correct', false)
            ->map(fn ($row) => [
                'question' => optional($row->question)->text ?? $row->question_text,
                'selected_answer' => optional($row->answer)->text ?? $row->answer_text,
            ])
            ->values();

        return response()->json([
            'data' => [
                'participant' => $participant,
                'rank' => $participantRank,
                'total_participants' => $leaderboard->count(),
                'score' => $participant->score,
                'answers_count' => $answers->count(),
                'correct_answers' => $answers->where('is_correct', true)->count(),
                'accuracy' => $answers->count() ? round($answers->where('is_correct', true)->count() / $answers->count() * 100) : 0,
                'mistakes' => $mistakes,
                'leaderboard' => $leaderboard,
                'session_status' => $session->status,
            ],
        ]);
    }

    private function leaderboardPayload($session)
    {
        return $session->participants()
            ->with('answers')
            ->orderByDesc('score')
            ->orderBy('updated_at')
            ->get()
            ->values()
            ->map(function (Participant $row, int $index) {
                $answersCount = $row->answers->count();
                $correctCount = $row->answers->where('is_correct', true)->count();

                return [
                    'rank' => $index + 1,
                    'id' => $row->id,
                    'name' => $row->name,
                    'avatar_color' => $row->avatar_color,
                    'score' => $row->score,
                    'answers_count' => $answersCount,
                    'correct_answers' => $correctCount,
                    'accuracy' => $answersCount ? round($correctCount / $answersCount * 100) : 0,
                ];
            });
    }

    private function authorizeParticipant(Request $request, Participant $participant): void
    {
        abort_unless(hash_equals($participant->access_token, (string) $request->query('token', $request->input('token'))), 403);
    }
}
