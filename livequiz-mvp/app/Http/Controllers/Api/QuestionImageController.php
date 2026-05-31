<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class QuestionImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageQuizzes(), 403, 'Загружать картинки может только ведущий или администратор.');

        $request->validate([
            'image' => ['nullable', 'file', 'image', 'max:5120'],
            'url' => ['nullable', 'url', 'max:2000'],
        ]);

        abort_if(! $request->hasFile('image') && ! $request->filled('url'), 422, 'Передайте файл или ссылку на картинку.');

        $storedUrl = $request->hasFile('image')
            ? $this->storeUploadedFile($request->file('image'))
            : $this->downloadAndStoreUrl($request->string('url')->toString());

        return response()->json(['url' => $storedUrl], 201);
    }

    private function storeUploadedFile(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');

        return $this->storeBytes(file_get_contents($file->getRealPath()), $extension);
    }

    private function downloadAndStoreUrl(string $url): string
    {
        $response = Http::timeout(8)->get($url);

        abort_unless($response->successful(), 422, 'Не удалось скачать картинку по ссылке.');

        $contentType = strtolower((string) $response->header('Content-Type'));
        abort_unless(str_starts_with($contentType, 'image/'), 422, 'Ссылка должна вести на изображение.');

        $extension = match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => 'jpg',
        };

        return $this->storeBytes($response->body(), $extension);
    }

    private function storeBytes(string $bytes, string $extension): string
    {
        abort_if(strlen($bytes) > 5 * 1024 * 1024, 422, 'Картинка не должна быть больше 5 МБ.');

        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $extension : 'jpg';
        $directory = public_path('uploads/question-images');
        File::ensureDirectoryExists($directory);

        $filename = Str::uuid().'.'.$extension;
        File::put($directory.DIRECTORY_SEPARATOR.$filename, $bytes);

        return url('/uploads/question-images/'.$filename);
    }
}
