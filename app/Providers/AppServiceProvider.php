<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // utf8mb4 index keys must fit older MySQL/MariaDB limits (767/1000 bytes) on shared hosting.
        Schema::defaultStringLength(191);

        Paginator::useBootstrapFive();
        Model::preventLazyLoading(false);

        Gate::define('admin', fn ($user) => $user->isAdmin());

        RateLimiter::for('game', fn (Request $r) => Limit::perMinute(240)->by($r->user()?->id ?: $r->ip()));
    }
}
