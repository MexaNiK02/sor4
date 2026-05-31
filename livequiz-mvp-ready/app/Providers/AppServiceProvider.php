<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::unguard();
        $this->ensureSqliteDatabaseExists();
    }

    private function ensureSqliteDatabaseExists(): void
    {
        if (config('database.default') !== 'sqlite') {
            return;
        }

        $path = config('database.connections.sqlite.database');

        if ($path === ':memory:' || File::exists($path)) {
            return;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, '');
    }
}
