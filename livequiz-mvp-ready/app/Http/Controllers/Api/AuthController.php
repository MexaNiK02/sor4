<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        return $this->registerWithRole($request, 'host');
    }

    public function registerParticipant(Request $request): JsonResponse
    {
        return $this->registerWithRole($request, 'participant');
    }

    public function login(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $payload['email'])->first();

        if (! $user || ! Hash::check($payload['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверная почта или пароль.'],
            ]);
        }

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $user->issueApiToken(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->forceFill(['api_token' => null])->save();

        return response()->json(['message' => 'Вы вышли из аккаунта.']);
    }

    public function participantHistory(Request $request): JsonResponse
    {
        abort_unless($request->user()->isParticipant(), 403, 'История доступна только аккаунту участника.');

        $rows = Participant::query()
            ->where('participant_user_id', $request->user()->id)
            ->with([
                'session.quiz:id,title,description',
                'session.participants.answers',
                'answers',
            ])
            ->latest()
            ->get()
            ->map(function (Participant $participant) {
                $session = $participant->session;
                $leaderboard = $session->participants
                    ->sortByDesc('score')
                    ->values()
                    ->map(fn (Participant $row, int $index) => [
                        'rank' => $index + 1,
                        'id' => $row->id,
                        'name' => $row->name,
                        'score' => $row->score,
                        'correct_answers' => $row->answers->where('is_correct', true)->count(),
                        'answers_count' => $row->answers->count(),
                    ]);

                $ownRow = $leaderboard->firstWhere('id', $participant->id);

                return [
                    'participant_id' => $participant->id,
                    'session_id' => $session->id,
                    'code' => $session->code,
                    'status' => $session->status,
                    'quiz' => $session->quiz,
                    'played_at' => optional($participant->created_at)->toIso8601String(),
                    'score' => $participant->score,
                    'rank' => $ownRow['rank'] ?? null,
                    'leaderboard' => $leaderboard,
                    'export_url' => url('/api/sessions/'.$session->id.'/export.csv'),
                ];
            });

        return response()->json(['data' => $rows]);
    }

    private function registerWithRole(Request $request, string $role): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user = User::create([
            'name' => $payload['name'],
            'email' => $payload['email'],
            'password' => $payload['password'],
            'role' => $role,
        ]);

        return response()->json([
            'user' => $this->userPayload($user),
            'token' => $user->issueApiToken(),
        ], 201);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
