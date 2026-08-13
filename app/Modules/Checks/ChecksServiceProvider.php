<?php

namespace App\Modules\Checks;

use App\Modules\Checks\Contracts\CheckRepository;
use App\Modules\Checks\Internal\EloquentCheckRepository;
use Illuminate\Support\ServiceProvider;

class ChecksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Снаружи виден только контракт: подменить реализацию можно, залезть
        // в Internal мимо неё — нет (AD-6).
        $this->app->bind(CheckRepository::class, EloquentCheckRepository::class);
    }

    public function boot(): void
    {
        // Laravel по умолчанию смотрит только в database/migrations. Схема
        // принадлежит модулю (AD-15), поэтому путь регистрируется явно.
        $this->loadMigrationsFrom(__DIR__.'/Migrations');
    }
}
