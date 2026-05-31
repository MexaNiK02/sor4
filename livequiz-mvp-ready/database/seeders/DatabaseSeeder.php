<?php

namespace Database\Seeders;

use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@livequiz.local'],
            [
                'name' => 'Администратор',
                'password' => 'admin123',
                'role' => 'admin',
            ]
        );

        $host = User::updateOrCreate(
            ['email' => 'host@livequiz.local'],
            [
                'name' => 'Преподаватель',
                'password' => 'host123',
                'role' => 'host',
            ]
        );

        $quiz = Quiz::updateOrCreate(
            [
                'user_id' => $host->id,
                'title' => 'Демо: цифровая грамотность',
            ],
            [
                'description' => 'Короткая викторина для демонстрации live-сессии, рейтинга и скорости ответа.',
                'host_name' => $host->name,
                'is_published' => true,
            ]
        );

        if ($quiz->questions()->exists()) {
            return;
        }

        $questions = [
            [
                'text' => 'Что лучше всего описывает MVP?',
                'type' => 'single_choice',
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
                'type' => 'single_choice',
                'timer_seconds' => 20,
                'answers' => [
                    ['Код, ссылка или QR-код', true],
                    ['Только регистрация через почту', false],
                    ['Ручная выдача логинов', false],
                    ['Файл Excel', false],
                ],
            ],
            [
                'text' => 'Что можно выбрать в вопросе с множественным выбором?',
                'type' => 'multiple_choice',
                'timer_seconds' => 30,
                'answers' => [
                    ['Несколько правильных вариантов', true],
                    ['Только один вариант', false],
                    ['Все подходящие ответы', true],
                    ['Ни одного ответа', false],
                ],
            ],
        ];

        foreach ($questions as $questionIndex => $item) {
            $question = $quiz->questions()->create([
                'text' => $item['text'],
                'type' => $item['type'],
                'timer_seconds' => $item['timer_seconds'],
                'image_urls' => [],
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
