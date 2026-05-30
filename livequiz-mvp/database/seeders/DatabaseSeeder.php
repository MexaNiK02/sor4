<?php

namespace Database\Seeders;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Администратор',
            'email' => 'admin@livequiz.local',
            'password' => 'admin123',
            'role' => 'admin',
        ]);

        $host = User::create([
            'name' => 'Преподаватель',
            'email' => 'host@livequiz.local',
            'password' => 'host123',
            'role' => 'host',
        ]);

        $quiz = Quiz::create([
            'user_id' => $host->id,
            'title' => 'Демо: цифровая грамотность',
            'description' => 'Короткая викторина для демонстрации live-сессии, рейтинга и скорости ответа.',
            'host_name' => $host->name,
            'is_published' => true,
        ]);

        $questions = [
            [
                'text' => 'Что лучше всего описывает MVP?',
                'timer_seconds' => 25,
                'answers' => [
                    ['Минимально жизнеспособный продукт', true],
                    ['Готовая корпоративная система', false],
                    ['Только дизайн-макет', false],
                    ['Отчет без приложения', false],
                ],
            ],
            [
                'text' => 'Какой способ подключения аудитории самый удобный для live-викторины?',
                'timer_seconds' => 20,
                'answers' => [
                    ['Код, ссылка или QR-код', true],
                    ['Только регистрация через почту', false],
                    ['Ручная выдача логинов', false],
                    ['Файл Excel', false],
                ],
            ],
            [
                'text' => 'За что участник получает больше баллов в режиме “молния”?',
                'timer_seconds' => 30,
                'answers' => [
                    ['За быстрый правильный ответ', true],
                    ['За самый длинный никнейм', false],
                    ['За пропуск вопроса', false],
                    ['За поздний неправильный ответ', false],
                ],
            ],
        ];

        foreach ($questions as $questionIndex => $item) {
            $question = $quiz->questions()->create([
                'text' => $item['text'],
                'timer_seconds' => $item['timer_seconds'],
                'position' => $questionIndex + 1,
            ]);

            foreach ($item['answers'] as $answerIndex => [$text, $isCorrect]) {
                $question->answers()->create([
                    'text' => $text,
                    'is_correct' => $isCorrect,
                    'position' => $answerIndex + 1,
                ]);
            }
        }
    }
}
