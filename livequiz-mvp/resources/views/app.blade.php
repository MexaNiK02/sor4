<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LiveQuiz</title>
    @vite(['resources/css/app.css', 'resources/js/App.jsx'])
</head>
<body>
    <div id="root"></div>
</body>
</html>
