# LiveQuiz MVP

LiveQuiz MVP - веб-приложение для live-викторин в стиле Kahoot. Ведущий регистрируется, создаёт квизы и запускает игровые сессии. Участники входят без регистрации по коду, ссылке или QR, отвечают на вопросы в реальном времени и видят итоговый рейтинг.

## Что нужно установить

Обязательно:

- PHP 8.1 или новее. Рекомендуется PHP 8.3.
- Composer 2.
- Node.js 20 или новее и npm.
- SQLite. Обычно расширение SQLite уже входит в PHP, но его нужно включить.

Для VPS на Linux дополнительно нужен веб-сервер:

- Nginx или Apache.
- PHP-FPM, если используется Nginx.

## Быстрый запуск на Windows

### 1. Установить PHP

1. Скачайте PHP 8.3 Thread Safe или Non Thread Safe для Windows с сайта `https://windows.php.net/download/`.
2. Распакуйте, например, в `C:\php83`.
3. Скопируйте `php.ini-development` в `php.ini`.
4. В `php.ini` включите расширения:

```ini
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=fileinfo
extension=mbstring
```

5. Добавьте `C:\php83` в системный `Path`.
6. Проверьте:

```powershell
php -v
```

### 2. Установить Composer

Скачайте Composer с `https://getcomposer.org/download/` и при установке укажите путь к `php.exe`, например:

```text
C:\php83\php.exe
```

Проверьте:

```powershell
composer -V
```

### 3. Установить Node.js

Скачайте LTS-версию Node.js с `https://nodejs.org/`.

Проверьте:

```powershell
node -v
npm -v
```

### 4. Собрать проект

Откройте PowerShell в папке проекта:

```powershell
cd путь\к\livequiz-mvp
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Откройте в браузере:

```text
http://127.0.0.1:8000
```

## Быстрый запуск на Linux

Пример для Ubuntu/Debian:

```bash
sudo apt update
sudo apt install -y php php-cli php-fpm php-sqlite3 php-mbstring php-xml php-curl php-zip unzip curl git
php -v
```

Установить Composer:

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer -V
```

Установить Node.js 20+:

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

Собрать проект:

```bash
cd /path/to/livequiz-mvp
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Откройте:

```text
http://127.0.0.1:8000
```

## Демо-аккаунты

После команды `php artisan migrate --seed` создаются:

- Ведущий: `host@livequiz.local` / `host123`
- Администратор: `admin@livequiz.local` / `admin123`

Участникам аккаунт не нужен. Они открывают `/join`, вводят код сессии и имя.

## Основные команды

Очистить кеш Laravel:

```bash
php artisan optimize:clear
```

Применить новые миграции без удаления данных:

```bash
php artisan migrate --force
```

Полностью пересоздать базу с демо-данными:

```bash
php artisan migrate:fresh --seed --force
```

Запустить тесты:

```bash
php artisan test
```

Собрать фронтенд:

```bash
npm run build
```

Запустить frontend dev-server:

```bash
npm run dev
```

В режиме разработки обычно запускают два терминала:

```bash
php artisan serve
npm run dev
```

## Развёртывание на VPS

1. Загрузите проект на сервер.
2. Выполните:

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan migrate --force
npm install
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. В `.env` укажите правильный `APP_URL`.
4. Настройте веб-сервер так, чтобы корнем сайта была папка `public`.
5. Для SQLite убедитесь, что PHP имеет права на запись в `database` и `storage`.

Пример прав на Linux:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache database
sudo chmod -R ug+rw storage bootstrap/cache database
```

## Переход с SQLite на MySQL или PostgreSQL

В `.env` замените настройки базы, например для MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=livequiz
DB_USERNAME=livequiz
DB_PASSWORD=secret
```

После этого выполните:

```bash
php artisan migrate --seed
```

## Что не входит в архив

Проект подготовлен для передачи без локальных тяжёлых файлов:

- `vendor` - ставится командой `composer install`.
- `node_modules` - ставится командой `npm install`.
- `public/build` - создаётся командой `npm run build`.
- `.env` - создаётся из `.env.example`.
- `database/database.sqlite` - создаётся автоматически при запуске Laravel и миграций.

Это нормальное состояние Laravel/React-проекта для передачи и последующей сборки.
