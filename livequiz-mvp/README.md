# LiveQuiz MVP

LiveQuiz MVP - веб-приложение для live-викторин в стиле Kahoot. Ведущий регистрируется, создаёт квизы и запускает игровые сессии. Участники могут играть без регистрации по коду, ссылке или QR-коду. Если участник зарегистрировался и вошёл в аккаунт, история его игр сохраняется в личном кабинете.

Проект состоит из Laravel API, React/Vite фронтенда, SQLite-базы для MVP и отдельного WebSocket-сервера на Node.js для синхронизации игры, таймера, вопросов и ответов.

## Возможности

- регистрация и вход ведущего;
- роль администратора;
- гостевое участие без регистрации;
- регистрация участника с историей игр;
- создание, редактирование и удаление квизов;
- одиночный и множественный выбор ответа;
- до 4 картинок в вопросе;
- загрузка картинок с компьютера или по URL;
- игровые сессии с кодом входа и QR-кодом;
- синхронизация через WebSocket;
- таймер вопроса и автоматический переход;
- итоговый рейтинг участников;
- экспорт результатов в CSV.

## Что нужно установить

Обязательно:

- PHP 8.1 или новее, рекомендуется PHP 8.3;
- Composer 2;
- Node.js 20 или новее и npm;
- SQLite-расширения для PHP;
- расширения PHP: `openssl`, `pdo_sqlite`, `sqlite3`, `fileinfo`, `mbstring`, `curl`, `xml`, `zip`.

Для размещения на VPS дополнительно нужен веб-сервер:

- Nginx или Apache;
- PHP-FPM, если используется Nginx;
- процесс-менеджер для WebSocket-сервера: `systemd`, `pm2` или supervisor.

## Переменные окружения

Файл `.env` создаётся из `.env.example`.

Важные настройки:

```env
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
VITE_WS_URL=ws://127.0.0.1:6001
LIVEQUIZ_WS_HOOK=http://127.0.0.1:6001/broadcast
LIVEQUIZ_IMAGE_VERIFY_SSL=false
```

`VITE_WS_URL` - адрес WebSocket-сервера для браузера.
`LIVEQUIZ_WS_HOOK` - внутренний HTTP hook, через который Laravel отправляет события в WebSocket-сервер.

На Windows у локального PHP часто не настроены корневые SSL-сертификаты для cURL, поэтому для разработки стоит `LIVEQUIZ_IMAGE_VERIFY_SSL=false`. На VPS/Linux с правильно настроенными сертификатами можно поставить `LIVEQUIZ_IMAGE_VERIFY_SSL=true`.

## Запуск на Windows

### 1. Установить PHP

1. Скачайте PHP 8.3 для Windows с `https://windows.php.net/download/`.
2. Распакуйте архив, например в `C:\php83`.
3. Скопируйте `php.ini-development` в `php.ini`.
4. В `php.ini` включите расширения:

```ini
extension=openssl
extension=pdo_sqlite
extension=sqlite3
extension=fileinfo
extension=mbstring
extension=curl
extension=zip
```

5. Добавьте `C:\php83` в системный `Path`.
6. Проверьте:

```powershell
php -v
```

Если `php` не найден, можно временно запускать команды полным путём:

```powershell
C:\php83\php.exe -v
```

### 2. Установить Composer

Скачайте Composer с `https://getcomposer.org/download/` и при установке укажите путь к PHP:

```text
C:\php83\php.exe
```

Проверка:

```powershell
composer -V
```

### 3. Установить Node.js

Скачайте LTS-версию Node.js с `https://nodejs.org/`.

Проверка:

```powershell
node -v
npm -v
```

### 4. Собрать проект

Откройте PowerShell в папке проекта:

```powershell
cd C:\path\to\livequiz-mvp
copy .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

### 5. Запустить приложение

Откройте два отдельных окна PowerShell.

Окно 1, Laravel:

```powershell
cd C:\path\to\livequiz-mvp
php artisan serve --host=127.0.0.1 --port=8000
```

Окно 2, WebSocket:

```powershell
cd C:\path\to\livequiz-mvp
npm run ws
```

Откройте приложение:

```text
http://127.0.0.1:8000
```

Для разработки фронтенда можно дополнительно запустить Vite:

```powershell
npm run dev
```

## Запуск на Ubuntu

### 1. Установить системные пакеты

```bash
sudo apt update
sudo apt install -y php php-cli php-fpm php-sqlite3 php-mbstring php-xml php-curl php-zip unzip curl git
php -v
```

### 2. Установить Composer

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer -V
```

### 3. Установить Node.js 20+

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

### 4. Собрать проект

```bash
cd /path/to/livequiz-mvp
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

### 5. Запустить локально

Терминал 1:

```bash
cd /path/to/livequiz-mvp
php artisan serve --host=127.0.0.1 --port=8000
```

Терминал 2:

```bash
cd /path/to/livequiz-mvp
npm run ws
```

Открыть:

```text
http://127.0.0.1:8000
```

## Запуск на РЕД ОС

Команды зависят от версии РЕД ОС и подключённых репозиториев. Обычно используется `dnf`; если в системе только `yum`, замените `dnf` на `yum`.

### 1. Установить системные пакеты

```bash
sudo dnf update -y
sudo dnf install -y php php-cli php-fpm php-pdo php-sqlite3 php-mbstring php-xml php-curl php-zip unzip curl git
php -v
```

Если пакет `php-sqlite3` не найден, попробуйте:

```bash
sudo dnf install -y php-sqlite
```

### 2. Установить Composer

```bash
cd /tmp
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php
sudo mv composer.phar /usr/local/bin/composer
composer -V
```

### 3. Установить Node.js 20+

Если Node.js 20 есть в репозиториях:

```bash
sudo dnf install -y nodejs npm
node -v
npm -v
```

Если версия Node.js ниже 20, установите Node.js через NodeSource:

```bash
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs
node -v
npm -v
```

### 4. Собрать проект

```bash
cd /path/to/livequiz-mvp
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

### 5. Запустить локально

Терминал 1:

```bash
cd /path/to/livequiz-mvp
php artisan serve --host=127.0.0.1 --port=8000
```

Терминал 2:

```bash
cd /path/to/livequiz-mvp
npm run ws
```

Открыть:

```text
http://127.0.0.1:8000
```

## Демо-аккаунты

После `php artisan migrate --seed` создаются:

- Ведущий: `host@livequiz.local` / `host123`
- Администратор: `admin@livequiz.local` / `admin123`

Участникам аккаунт не обязателен. Они открывают `/join`, вводят код сессии и имя.

Если участник хочет сохранять историю игр, он может зарегистрироваться и войти через `/participant/login`. После этого его игры будут доступны в `/participant/history`.

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

Запустить Vite dev-server:

```bash
npm run dev
```

Запустить WebSocket-сервер:

```bash
npm run ws
```

## Развёртывание на VPS

### 1. Подготовить проект

```bash
cd /var/www/livequiz-mvp
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
npm install
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

В `.env` укажите реальные адреса:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
VITE_WS_URL=wss://example.com/ws
LIVEQUIZ_WS_HOOK=http://127.0.0.1:6001/broadcast
LIVEQUIZ_IMAGE_VERIFY_SSL=true
```

Для SQLite убедитесь, что PHP имеет права на запись:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache database
sudo chmod -R ug+rw storage bootstrap/cache database
```

На РЕД ОС пользователь веб-сервера может называться `apache`:

```bash
sudo chown -R apache:apache storage bootstrap/cache database
sudo chmod -R ug+rw storage bootstrap/cache database
```

### 2. Настроить веб-сервер

Корнем сайта должна быть папка:

```text
/var/www/livequiz-mvp/public
```

Laravel должен обслуживаться через PHP-FPM. WebSocket-сервер запускается отдельно и должен быть доступен браузеру по адресу из `VITE_WS_URL`.

### 3. Запустить WebSocket через systemd

Пример unit-файла:

```ini
[Unit]
Description=LiveQuiz WebSocket Server
After=network.target

[Service]
WorkingDirectory=/var/www/livequiz-mvp
ExecStart=/usr/bin/npm run ws
Restart=always
RestartSec=3
User=www-data
Group=www-data
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
```

Для РЕД ОС замените пользователя и группу на `apache`, если веб-сервер работает от него:

```ini
User=apache
Group=apache
```

Сохраните файл:

```bash
sudo nano /etc/systemd/system/livequiz-ws.service
```

Запуск:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now livequiz-ws
sudo systemctl status livequiz-ws
```

Логи:

```bash
journalctl -u livequiz-ws -f
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

## Что не передаётся вместе с исходниками

Эти файлы и папки не нужны для передачи проекта, потому что создаются заново при установке:

- `vendor` - создаётся командой `composer install`;
- `node_modules` - создаётся командой `npm install`;
- `public/build` - создаётся командой `npm run build`;
- `public/uploads/question-images/*` - пользовательские загруженные картинки;
- `.env` - создаётся из `.env.example`;
- `database/database.sqlite` - создаётся автоматически при запуске Laravel и миграций;
- `bootstrap/cache/*.php` - кеш Laravel;
- `storage/logs/*.log` и временные файлы `storage/framework`.

В репозитории должны оставаться исходники, миграции, сидеры, тесты, `composer.lock`, `package-lock.json`, README и `ARCHITECTURE.md`.
