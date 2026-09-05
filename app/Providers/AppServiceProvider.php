<?php

namespace App\Providers;

use App\Services\AiMemory\AiMemoryDatabase;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One instance per request: memoises isAvailable() (a single stat + probe
        // SELECT) and the degraded state across every repository, so one failure
        // is not retried screen-wide.
        $this->app->singleton(AiMemoryDatabase::class);
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
        $this->shareAppVersion();
        $this->usePlainPagination();
    }

    /**
     * Pagination with this app's own tokens.
     *
     * Laravel's default is `pagination::tailwind` — utility classes and SVGs
     * sized by Tailwind. This app does NOT load Tailwind (the theme is plain
     * CSS in the layout), so those `<svg class="w-5 h-5">` arrows would render
     * at the size of their container. See
     * resources/views/vendor/pagination/plain.blade.php.
     */
    private function usePlainPagination(): void
    {
        Paginator::defaultView('vendor.pagination.plain');
        Paginator::defaultSimpleView('vendor.pagination.plain-simple');
    }

    /**
     * Expose the app version (root `version.md`) to the layout. A view composer
     * rather than config('app.version') on purpose: it runs at request time, so
     * `config:cache` during a deploy cannot bake a stale value in. The file is
     * read once per process (memoised in the static).
     */
    private function shareAppVersion(): void
    {
        View::composer('layouts.app', function ($view) {
            static $version = null;
            if ($version === null) {
                $raw = @file_get_contents(base_path('version.md'));
                $version = $raw !== false ? trim($raw) : '';
            }
            $view->with('appVersion', $version);
        });
    }

    /** Login attempts: 5/min per (IP + e-mail). */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)
            ->by($r->ip().'|'.Str::lower((string) $r->input('email'))));
    }
}
