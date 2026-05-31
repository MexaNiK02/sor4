<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Participant;
use App\Models\User;
use App\Services\LiveQuizBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class SessionController extends Controller
{
    public function showByCode(Request $request, string $code): JsonResponse
    {
        $session = GameSession::where('code', Str::upper($code))
            ->with('quiz.questions.answers', 'currentQuestion.answers', 'participants.answers')
            ->firstOrFail()
            ->refreshTimedState();
        $this->authorizeSessionAccess($request->user(), $session);

        return response()->json([
            'data' => $this->sessionPayload($session),
        ]);
    }

    public function join(Request $request, string $code, LiveQuizBroadcaster $broadcaster): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:40'],
        ]);

        $session = GameSession::where('code', Str::upper($code))->active()->firstOrFail()->refreshTimedState();
        abort_if($session->status === 'finished', 422, 'Сессия уже завершена.');

        $account = $this->optionalParticipantAccount($request);

        $participant = Participant::create([
            'game_session_id' => $session->id,
            'participant_user_id' => $account?->id,
            'name' => $payload['name'],
            'avatar_color' => collect(['#2563eb', '#059669', '#dc2626', '#7c3aed', '#ea580c', '#0f766e'])->random(),
            'access_token' => Str::random(48),
            'last_seen_at' => now(),
        ]);

        $broadcaster->sessionUpdated($session->fresh(), 'participant.joined');

        return response()->json([
            'data' => $participant,
            'token' => $participant->access_token,
            'session' => $this->sessionPayload($session->fresh()),
        ], 201);
    }

    public function start(Request $request, GameSession $session, LiveQuizBroadcaster $broadcaster): JsonResponse
    {
        $this->authorizeSessionAccess($request->user(), $session);
        $firstQuestion = $session->quiz->questions()->firstOrFail();
        $session = $session->startQuestion($firstQuestion);
        $broadcaster->sessionUpdated($session, 'session.started');

        return response()->json([
            'data' => $this->sessionPayload($session),
        ]);
    }

    public function advance(Request $request, GameSession $session, LiveQuizBroadcaster $broadcaster): JsonResponse
    {
        $this->authorizeSessionAccess($request->user(), $session);
        abort_if($session->status !== 'active', 422, 'Сессия не активна.');

        $session = $session->moveToNextQuestion();
        $broadcaster->sessionUpdated($session, 'session.advanced');

        return response()->json([
            'data' => $this->sessionPayload($session),
        ]);
    }

    public function finish(Request $request, GameSession $session, LiveQuizBroadcaster $broadcaster): JsonResponse
    {
        $this->authorizeSessionAccess($request->user(), $session);
        $session->update([
            'status' => 'finished',
            'current_phase' => 'finished',
            'finished_at' => now(),
        ]);
        $session = $session->fresh();
        $broadcaster->sessionUpdated($session, 'session.finished');

        return response()->json(['data' => $this->sessionPayload($session)]);
    }

    public function leaderboard(Request $request, GameSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($request->user(), $session, allowParticipant: true);
        $session = $session->refreshTimedState();

        return response()->json([
            'data' => $this->leaderboardPayload($session),
        ]);
    }

    public function answerStats(Request $request, GameSession $session): JsonResponse
    {
        $this->authorizeSessionAccess($request->user(), $session);
        $session = $session->refreshTimedState();
        $questionId = $request->integer('question_id') ?: $session->current_question_id;
        $question = $session->quiz->questions()->with('answers')->findOrFail($questionId);
        $participantAnswers = $question->participantAnswers()
            ->whereHas('participant', fn ($query) => $query->where('game_session_id', $session->id))
            ->get();

        $stats = $question->answers->map(function ($answer) use ($participantAnswers) {
            return [
                'answer_id' => $answer->id,
                'text' => $answer->text,
                'is_correct' => $answer->is_correct,
                'count' => $participantAnswers->filter(fn ($row) => in_array($answer->id, $row->selected_answer_ids ?? [], true))->count(),
            ];
        });

        return response()->json(['data' => $stats]);
    }

    public function exportCsv(Request $request, GameSession $session)
    {
        $this->authorizeSessionAccess($request->user(), $session, allowParticipant: true);
        $rows = $this->leaderboardPayload($session->refreshTimedState());
        $filename = 'livequiz-'.$session->code.'-results.csv';

        return Response::streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Место', 'Имя', 'Баллы', 'Правильных', 'Всего ответов', 'Успешность', 'Бейджи'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['rank'],
                    $row['name'],
                    $row['score'],
                    $row['correct_answers'],
                    $row['answers_count'],
                    $row['accuracy'].'%',
                    implode(', ', $row['badges']),
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function sessionPayload(GameSession $session): array
    {
        $session = $session->refreshTimedState();
        $session->load('quiz.questions.answers', 'currentQuestion.answers', 'participants.answers');

        return [
            'id' => $session->id,
            'code' => $session->code,
            'status' => $session->status,
            'current_phase' => $session->current_phase,
            'join_url' => url('/join?code='.$session->code),
            'started_at' => optional($session->started_at)->toIso8601String(),
            'finished_at' => optional($session->finished_at)->toIso8601String(),
            'current_question_started_at' => optional($session->current_question_started_at)->toIso8601String(),
            'reveal_started_at' => optional($session->reveal_started_at)->toIso8601String(),
            'reveal_ends_at' => optional($session->reveal_ends_at)->toIso8601String(),
            'question_seconds_remaining' => $session->questionSecondsRemaining(),
            'reveal_seconds_remaining' => $session->revealSecondsRemaining(),
            'quiz' => $session->quiz,
            'current_question' => $session->currentQuestion,
            'participants' => $session->participants,
            'leaderboard' => $this->leaderboardPayload($session),
        ];
    }

    private function leaderboardPayload(GameSession $session)
    {
        $questionCount = max(1, $session->quiz->questions()->count());
        $participants = $session->participants()
            ->with('answers')
            ->orderByDesc('score')
            ->orderBy('updated_at')
            ->get();

        return $participants->values()->map(function (Participant $participant, int $index) use ($questionCount) {
            $answersCount = $participant->answers->count();
            $correctCount = $participant->answers->where('is_correct', true)->count();
            $fastest = $participant->answers->where('is_correct', true)->min('response_ms');

            $badges = [];
            if ($fastest !== null && $fastest <= 5000) {
                $badges[] = 'Молния';
            }
            if ($answersCount >= $questionCount && $correctCount === $questionCount) {
                $badges[] = 'Без ошибок';
            }
            if ($participant->answers->last()?->is_correct && $participant->score >= 100) {
                $badges[] = 'Финишный рывок';
            }

            return [
                'rank' => $index + 1,
                'id' => $participant->id,
                'name' => $participant->name,
                'avatar_color' => $participant->avatar_color,
                'score' => $participant->score,
                'answers_count' => $answersCount,
                'correct_answers' => $correctCount,
                'accuracy' => $answersCount ? round($correctCount / $answersCount * 100) : 0,
                'badges' => $badges,
            ];
        });
    }

    private function authorizeSessionAccess(User $user, GameSession $session, bool $allowParticipant = false): void
    {
        $quiz = $session->quiz()->firstOrFail();

        if ($user->isAdmin() || $quiz->user_id === $user->id) {
            return;
        }

        if ($allowParticipant && $user->isParticipant()) {
            $played = $session->participants()->where('participant_user_id', $user->id)->exists();
            abort_unless($played, 403, 'Нет доступа к этой игровой сессии.');

            return;
        }

        abort(403, 'Нет доступа к этой игровой сессии.');
    }

    private function optionalParticipantAccount(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $user = User::where('api_token', $token)->first();

        return $user?->isParticipant() ? $user : null;
    }
}
