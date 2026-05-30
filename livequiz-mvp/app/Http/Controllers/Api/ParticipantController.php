<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Participant;
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
                        'score' => (int) optional($participantAnswer)->score,
                    ] : null,
                ],
            ],
        ]);
    }

    public function answer(Request $request, Participant $participant): JsonResponse
    {
        $this->authorizeParticipant($request, $participant);

        $payload = $request->validate([
            'answer_id' => ['required', 'integer', 'exists:answers,id'],
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

        $answer = Answer::findOrFail($payload['answer_id']);
        abort_if($answer->question_id !== $session->current_question_id, 422, 'Ответ не относится к текущему вопросу.');

        $responseMs = now()->diffInMilliseconds($session->current_question_started_at);
        $maxMs = max(1, $session->currentQuestion->timer_seconds * 1000);
        $speedBonus = $answer->is_correct ? max(0, (int) round((1 - min($responseMs, $maxMs) / $maxMs) * 50)) : 0;
        $score = $answer->is_correct ? 100 + $speedBonus : 0;

        $participantAnswer = $participant->answers()->create([
            'question_id' => $session->current_question_id,
            'answer_id' => $answer->id,
            'question_text' => $session->currentQuestion->text,
            'answer_text' => $answer->text,
            'is_correct' => $answer->is_correct,
            'score' => $score,
            'response_ms' => $responseMs,
            'answered_at' => now(),
        ]);

        $participant->increment('score', $score);

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
