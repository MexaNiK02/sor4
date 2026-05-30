<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('livequiz:about', function () {
    $this->info('LiveQuiz MVP: Laravel API + React live quiz frontend.');
});
