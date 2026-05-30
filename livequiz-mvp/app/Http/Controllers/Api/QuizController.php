<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Quiz::with('owner:id,name,email,role')
            ->withCount(['questions', 'sessions'])
            ->latest();

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function show(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorizeQuizAccess($request->user(), $quiz);

        return response()->json([
            'data' => $quiz->load('owner:id,name,email,role', 'questions.answers'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validateQuiz($request);
        $user = $request->user();

        $quiz = DB::transaction(fn () => $this->persistQuiz(new Quiz(), $payload, $user));

        return response()->json(['data' => $quiz->load('owner:id,name,email,role', 'questions.answers')], 201);
    }

    public function update(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorizeQuizEdit($request->user(), $quiz);
        $payload = $this->validateQuiz($request);

        $quiz = DB::transaction(fn () => $this->persistQuiz($quiz, $payload, $request->user()));

        return response()->json(['data' => $quiz->load('owner:id,name,email,role', 'questions.answers')]);
    }

    public function destroy(Request $request, Quiz $quiz): JsonResponse
    {
        abort_unless($quiz->user_id === $request->user()->id, 403, 'Удалить квиз может только его создатель.');

        $quiz->delete();

        return response()->json(['message' => 'Квиз удален.']);
    }

    public function startSession(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorizeQuizEdit($request->user(), $quiz);
        abort_if($quiz->questions()->count() === 0, 422, 'Добавьте хотя бы один вопрос.');

        $session = GameSession::create([
            'quiz_id' => $quiz->id,
            'code' => $this->makeUniqueCode(),
            'status' => 'waiting',
            'current_phase' => 'waiting',
        ]);

        return response()->json([
            'data' => $session->load('quiz.questions.answers', 'participants'),
            'join_url' => url('/join?code='.$session->code),
        ], 201);
    }

    public function sessions(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorizeQuizAccess($request->user(), $quiz);

        $sessions = $quiz->sessions()
            ->with('participants.answers')
            ->latest()
            ->get()
            ->map(fn (GameSession $session) => [
                'id' => $session->id,
                'code' => $session->code,
                'status' => $session->status,
                'started_at' => optional($session->started_at)->toIso8601String(),
                'finished_at' => optional($session->finished_at)->toIso8601String(),
                'participants_count' => $session->participants->count(),
                'leaderboard' => $this->leaderboardPayload($session),
                'export_url' => url('/api/sessions/'.$session->id.'/export.csv'),
            ]);

        return response()->json(['data' => $sessions]);
    }

    private function validateQuiz(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'host_name' => ['nullable', 'string', 'max:100'],
            'is_published' => ['boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.text' => ['required', 'string', 'max:1000'],
            'questions.*.type' => ['nullable', 'string', 'in:single_choice'],
            'questions.*.timer_seconds' => ['required', 'integer', 'min:10', 'max:120'],
            'questions.*.answers' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.answers.*.text' => ['required', 'string', 'max:255'],
            'questions.*.answers.*.is_correct' => ['boolean'],
        ]);
    }

    private function persistQuiz(Quiz $quiz, array $payload, User $user): Quiz
    {
        $quiz->fill([
            'user_id' => $quiz->exists ? $quiz->user_id : $user->id,
            'title' => $payload['title'],
            'description' => $payload['description'] ?? null,
            'host_name' => $payload['host_name'] ?? $user->name,
            'is_published' => $payload['is_published'] ?? true,
        ])->save();

        $quiz->questions()->delete();

        foreach ($payload['questions'] as $questionIndex => $questionPayload) {
            $answers = collect($questionPayload['answers']);
            abort_if($answers->where('is_correct', true)->count() !== 1, 422, 'У каждого вопроса должен быть один правильный ответ.');

            $question = $quiz->questions()->create([
                'text' => $questionPayload['text'],
                'type' => $questionPayload['type'] ?? 'single_choice',
                'timer_seconds' => $questionPayload['timer_seconds'],
                'position' => $questionIndex + 1,
            ]);

            foreach ($answers as $answerIndex => $answerPayload) {
                $question->answers()->create([
                    'text' => $answerPayload['text'],
                    'is_correct' => (bool) ($answerPayload['is_correct'] ?? false),
                    'position' => $answerIndex + 1,
                ]);
            }
        }

        return $quiz->refresh();
    }

    private function authorizeQuizAccess(User $user, Quiz $quiz): void
    {
        abort_unless($user->isAdmin() || $quiz->user_id === $user->id, 403, 'Нет доступа к этому квизу.');
    }

    private function authorizeQuizEdit(User $user, Quiz $quiz): void
    {
        abort_unless($user->isAdmin() || $quiz->user_id === $user->id, 403, 'Нет прав на редактирование этого квиза.');
    }

    private function leaderboardPayload(GameSession $session)
    {
        return $session->participants
            ->sortByDesc('score')
            ->values()
            ->map(fn ($participant, $index) => [
                'rank' => $index + 1,
                'id' => $participant->id,
                'name' => $participant->name,
                'score' => $participant->score,
                'correct_answers' => $participant->answers->where('is_correct', true)->count(),
                'answers_count' => $participant->answers->count(),
            ]);
    }

    private function makeUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (GameSession::where('code', $code)->exists());

        return $code;
    }
}
