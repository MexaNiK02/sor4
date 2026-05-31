# Быстрый запуск готовой копии

Эта папка содержит уже установленный проект:

- PHP-зависимости в `vendor`;
- Node.js-зависимости в `node_modules`;
- собранный фронтенд в `public/build`;
- готовый `.env`;
- созданную SQLite-базу `database/database.sqlite` с демо-аккаунтами.

Для запуска нужны только установленные PHP 8.1+ и Node.js 20+.

## Windows

Окно 1:

```powershell
cd C:\path\to\livequiz-mvp-ready
php artisan serve --host=127.0.0.1 --port=8000
```

Окно 2:

```powershell
cd C:\path\to\livequiz-mvp-ready
npm run ws
```

Открыть:

```text
http://127.0.0.1:8000
```

## Linux

Терминал 1:

```bash
cd /path/to/livequiz-mvp-ready
php artisan serve --host=127.0.0.1 --port=8000
```

Терминал 2:

```bash
cd /path/to/livequiz-mvp-ready
npm run ws
```

Открыть:

```text
http://127.0.0.1:8000
```

## Демо-аккаунты

- Ведущий: `host@livequiz.local` / `host123`
- Администратор: `admin@livequiz.local` / `admin123`
