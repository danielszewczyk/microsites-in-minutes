<?php

namespace App\Providers;

use App\Domains\DomainProvisioner;
use App\Domains\StubProvisioner;
use App\Leads\BouncerVerifier;
use App\Leads\EmailVerifier;
use App\Leads\NullPhoneVerifier;
use App\Leads\NullVerifier;
use App\Leads\PhoneVerifier;
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

        /*
         * How a bought vanity domain is registered and pointed at us. The stub
         * simulates it (no registrar call, no charge); swap for a real registrar
         * + Cloudflare provisioner at deploy.
         */
        $this->app->bind(
            DomainProvisioner::class,
            match (config('domains.provider', 'stub')) {
                default => StubProvisioner::class,
            },
        );

        /*
         * Email deliverability verification: Bouncer when a key is set, else a
         * no-op verifier that marks entries "unverified".
         */
        $this->app->bind(EmailVerifier::class, function (): EmailVerifier {
            $key = config('services.bouncer.key');

            return $key ? new BouncerVerifier($key) : new NullVerifier;
        });

        /*
         * Phone-number lookup verification. A real lookup provider (e.g. Twilio
         * Lookup) is wired at deploy; until then the no-op verifier is used.
         */
        $this->app->bind(PhoneVerifier::class, NullPhoneVerifier::class);
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

        // The consent screen shown when a connector (e.g. Grok) asks to connect.
        Passport::authorizationView('oauth.authorize');
    }
}
