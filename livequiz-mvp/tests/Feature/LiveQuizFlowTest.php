<?php

namespace Tests\Feature;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveQuizFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_create_session_and_participant_can_answer_without_registration(): void
    {
        $headers = $this->hostHeaders();

        $quizResponse = $this->withHeaders($headers)->postJson('/api/quizzes', [
            'title' => 'Тестовый квиз',
            'description' => 'Проверка основного сценария',
            'questions' => [
                [
                    'text' => 'Сколько будет 2 + 2?',
                    'timer_seconds' => 20,
                    'answers' => [
                        ['text' => '3', 'is_correct' => false],
                        ['text' => '4', 'is_correct' => true],
                    ],
                ],
            ],
        ])->assertCreated();

        $quizId = $quizResponse->json('data.id');
        $answerId = $quizResponse->json('data.questions.0.answers.1.id');

        $sessionResponse = $this->withHeaders($headers)->postJson("/api/quizzes/{$quizId}/sessions")->assertCreated();
        $sessionId = $sessionResponse->json('data.id');
        $code = $sessionResponse->json('data.code');

        $joinResponse = $this->postJson("/api/sessions/{$code}/join", ['name' => 'Алина'])->assertCreated();
        $participantId = $joinResponse->json('data.id');
        $token = $joinResponse->json('token');

        $secondJoinResponse = $this->postJson("/api/sessions/{$code}/join", ['name' => 'Марк'])->assertCreated();
        $secondParticipantId = $secondJoinResponse->json('data.id');
        $secondToken = $secondJoinResponse->json('token');

        $this->withHeaders($headers)->postJson("/api/sessions/{$sessionId}/start")->assertOk();

        $this->postJson("/api/participants/{$participantId}/answers?token={$token}", [
            'answer_id' => $answerId,
        ])->assertCreated();

        $this->postJson("/api/participants/{$secondParticipantId}/answers?token={$secondToken}", [
            'answer_id' => $quizResponse->json('data.questions.0.answers.0.id'),
        ])->assertCreated();

        $this->withHeaders($headers)->postJson("/api/sessions/{$sessionId}/finish")->assertOk();

        $this->getJson("/api/participants/{$participantId}/result?token={$token}")
            ->assertOk()
            ->assertJsonPath('data.correct_answers', 1)
            ->assertJsonPath('data.leaderboard.0.name', 'Алина')
            ->assertJsonPath('data.leaderboard.1.name', 'Марк');
    }

    public function test_question_timeout_reveals_answer_then_advances_or_finishes(): void
    {
        $headers = $this->hostHeaders('timer-host@example.com');

        $quizResponse = $this->withHeaders($headers)->postJson('/api/quizzes', [
            'title' => 'Квиз с таймером',
            'questions' => [
                [
                    'text' => 'Первый вопрос',
                    'timer_seconds' => 10,
                    'answers' => [
                        ['text' => 'Верно', 'is_correct' => true],
                        ['text' => 'Неверно', 'is_correct' => false],
                    ],
                ],
                [
                    'text' => 'Второй вопрос',
                    'timer_seconds' => 10,
                    'answers' => [
                        ['text' => 'Да', 'is_correct' => true],
                        ['text' => 'Нет', 'is_correct' => false],
                    ],
                ],
            ],
        ])->assertCreated();

        $quizId = $quizResponse->json('data.id');
        $sessionResponse = $this->withHeaders($headers)->postJson("/api/quizzes/{$quizId}/sessions")->assertCreated();
        $sessionId = $sessionResponse->json('data.id');
        $code = $sessionResponse->json('data.code');

        $this->withHeaders($headers)->postJson("/api/sessions/{$sessionId}/start")->assertOk();

        \App\Models\GameSession::find($sessionId)->update([
            'current_question_started_at' => now()->subSeconds(11),
        ]);

        $this->withHeaders($headers)->getJson("/api/sessions/{$code}")
            ->assertOk()
            ->assertJsonPath('data.current_phase', 'reveal');

        \App\Models\GameSession::find($sessionId)->update([
            'reveal_ends_at' => now()->subSecond(),
        ]);

        $this->withHeaders($headers)->getJson("/api/sessions/{$code}")
            ->assertOk()
            ->assertJsonPath('data.current_phase', 'question')
            ->assertJsonPath('data.current_question.position', 2);
    }

    public function test_hosts_are_isolated_and_admin_can_edit_but_not_delete_foreign_quizzes(): void
    {
        $hostA = $this->registerHost('host-a@example.com');
        $hostB = $this->registerHost('host-b@example.com');

        $quizResponse = $this->withHeaders($hostA['headers'])->postJson('/api/quizzes', $this->quizPayload('Квиз ведущего A'))
            ->assertCreated();

        $quizId = $quizResponse->json('data.id');

        $this->withHeaders($hostB['headers'])->getJson('/api/quizzes')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withHeaders($hostB['headers'])->getJson("/api/quizzes/{$quizId}")
            ->assertForbidden();

        $admin = User::create([
            'name' => 'Администратор',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'role' => 'admin',
        ]);
        $adminToken = $admin->issueApiToken();
        $adminHeaders = ['Authorization' => "Bearer {$adminToken}"];

        $this->withHeaders($adminHeaders)->getJson('/api/quizzes')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withHeaders($adminHeaders)->putJson("/api/quizzes/{$quizId}", $this->quizPayload('Квиз после правки админа'))
            ->assertOk()
            ->assertJsonPath('data.user_id', $hostA['user_id']);

        $this->withHeaders($adminHeaders)->deleteJson("/api/quizzes/{$quizId}")
            ->assertForbidden();

        $this->withHeaders($hostA['headers'])->deleteJson("/api/quizzes/{$quizId}")
            ->assertOk();

        $this->assertDatabaseMissing('quizzes', ['id' => $quizId]);
    }

    public function test_quiz_history_keeps_leaderboard_after_quiz_is_edited(): void
    {
        $headers = $this->hostHeaders('history-host@example.com');

        $quizResponse = $this->withHeaders($headers)->postJson('/api/quizzes', $this->quizPayload('Исторический квиз'))
            ->assertCreated();
        $quizId = $quizResponse->json('data.id');
        $answerId = $quizResponse->json('data.questions.0.answers.0.id');

        $sessionResponse = $this->withHeaders($headers)->postJson("/api/quizzes/{$quizId}/sessions")->assertCreated();
        $sessionId = $sessionResponse->json('data.id');
        $code = $sessionResponse->json('data.code');

        $joinResponse = $this->postJson("/api/sessions/{$code}/join", ['name' => 'История'])->assertCreated();
        $participantId = $joinResponse->json('data.id');
        $participantToken = $joinResponse->json('token');

        $this->withHeaders($headers)->postJson("/api/sessions/{$sessionId}/start")->assertOk();
        $this->postJson("/api/participants/{$participantId}/answers?token={$participantToken}", [
            'answer_id' => $answerId,
        ])->assertCreated();

        $this->withHeaders($headers)->putJson("/api/quizzes/{$quizId}", $this->quizPayload('Квиз после редактирования'))
            ->assertOk();

        $this->withHeaders($headers)->getJson("/api/quizzes/{$quizId}/sessions")
            ->assertOk()
            ->assertJsonPath('data.0.leaderboard.0.name', 'История')
            ->assertJsonPath('data.0.leaderboard.0.correct_answers', 1);

        $this->getJson("/api/participants/{$participantId}/result?token={$participantToken}")
            ->assertOk()
            ->assertJsonPath('data.correct_answers', 1);
    }

    public function test_csv_export_is_utf8_for_excel(): void
    {
        $headers = $this->hostHeaders('csv-host@example.com');

        $quizResponse = $this->withHeaders($headers)->postJson('/api/quizzes', $this->quizPayload('CSV квиз'))
            ->assertCreated();
        $quizId = $quizResponse->json('data.id');

        $sessionResponse = $this->withHeaders($headers)->postJson("/api/quizzes/{$quizId}/sessions")
            ->assertCreated();
        $sessionId = $sessionResponse->json('data.id');

        $response = $this->withHeaders($headers)->get("/api/sessions/{$sessionId}/export.csv")
            ->assertOk();

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Место;Имя;Баллы', $content);
    }

    private function hostHeaders(string $email = 'host@example.com'): array
    {
        return $this->registerHost($email)['headers'];
    }

    private function registerHost(string $email): array
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ведущий',
            'email' => $email,
            'password' => 'secret123',
        ])->assertCreated();

        return [
            'headers' => ['Authorization' => 'Bearer '.$response->json('token')],
            'user_id' => $response->json('user.id'),
        ];
    }

    private function quizPayload(string $title): array
    {
        return [
            'title' => $title,
            'description' => 'Описание',
            'questions' => [
                [
                    'text' => 'Вопрос',
                    'timer_seconds' => 10,
                    'answers' => [
                        ['text' => 'Правильно', 'is_correct' => true],
                        ['text' => 'Неправильно', 'is_correct' => false],
                    ],
                ],
            ],
        ];
    }
}
