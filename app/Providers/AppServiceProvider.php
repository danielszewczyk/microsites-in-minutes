<?php

namespace App\Providers;

use App\Sites\Copywriter\Copywriter;
use App\Sites\Copywriter\TemplateCopywriter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * The generation seam. Swap this binding for an LLM-backed writer to
         * change how prompts become copy; nothing else in the app changes.
         */
        $this->app->bind(Copywriter::class, TemplateCopywriter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Behind Cloud's TLS-terminating proxy the app sees http; force https
         * link and asset generation when the app URL says so.
         */
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        /*
         * The one scope the MCP connector asks for: create and manage the
         * connected account's microsites.
         */
        Passport::tokensCan(['mcp:use' => 'Create and manage your microsites']);
        Passport::setDefaultScope(['mcp:use']);
    }
}
