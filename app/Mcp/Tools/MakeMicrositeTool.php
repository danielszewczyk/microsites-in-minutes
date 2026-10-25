<?php

namespace App\Mcp\Tools;

use App\Models\Owner;
use App\Models\Site;
use App\Sites\HeroArt;
use App\Sites\SiteComposer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('make-microsite')]
#[Title('Make a Microsite')]
#[Description(<<<'TXT'
Use whenever the user asks you to make, build, launch or create a promo page,
landing page, giveaway or sweepstakes microsite for a business or idea. You
describe the promotion in a short prompt; the tool writes the page, publishes
it and returns the live URL. Do not read the URL aloud: open it on the user's
screen if you can, and just say the site is live. Keep the URL to pass back
when changing the site.

To CHANGE a site you already made, pass `site` (the URL or the last part of it)
and only the fields to change. The same link updates and a page open on it
refreshes itself within seconds, so never make a new site when the user asks to
change the one on screen.

A new site needs a business_name and a prompt (one or two plain sentences on
what the promo is and who it is for). type is "standard" for a promo/landing
page or "sweepstakes" for a giveaway. For a sweepstakes also give prize, and
ideally prize_value, start_date, end_date and who may enter (eligibility); the
tool writes the official rules and a free alternate method of entry. Keep prizes
modest (products or swag, never free or discounted services) and, for a
giveaway, limit eligibility to one state to keep it simple.

accent_color is an optional hex colour like #0f766e. hero_image picks a built-in
drawing (smile for a dentist, paw for a vet, coffee for a cafe) or "auto" to let
the tool choose. Write plain text only: no HTML, no markdown.
TXT)]
class MakeMicrositeTool extends Tool
{
    public function handle(Request $request, SiteComposer $composer): Response
    {
        $owner = $this->owner();

        if ($owner === null) {
            return Response::error('No connected account. Reconnect the Microsites in Minutes connector and try again.');
        }

        $updating = trim((string) $request->get('site', '')) !== '';
        $required = $updating ? 'sometimes' : 'required';
        $isSweepstakes = $request->get('type') === Site::TYPE_SWEEPSTAKES;

        $validated = $request->validate([
            'site' => ['nullable', 'string', 'max:200'],
            'business_name' => [$required, 'string', 'min:2', 'max:80'],
            'prompt' => [$required, 'string', 'min:10', 'max:1500'],
            'type' => ['nullable', 'in:'.Site::TYPE_STANDARD.','.Site::TYPE_SWEEPSTAKES],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'hero_image' => ['nullable', 'in:auto,none,'.implode(',', HeroArt::ILLUSTRATIONS)],
            'offer_headline' => ['nullable', 'string', 'max:120'],
            'cta_label' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'prize' => ['nullable', $isSweepstakes && ! $updating ? 'required' : 'sometimes', 'string', 'max:300'],
            'prize_value' => ['nullable', 'string', 'max:60'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'eligibility' => ['nullable', 'string', 'max:400'],
        ], [
            'accent_color.regex' => 'accent_color must be a hex colour like #0f766e, or left out.',
        ]);

        return $updating
            ? $this->update($owner, $validated, $composer)
            : $this->create($owner, $validated, $composer);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function create(Owner $owner, array $validated, SiteComposer $composer): Response
    {
        try {
            ['site' => $site, 'token' => $token] = $composer->publish([
                ...$validated,
                'owner_email' => $owner->email,
                'owner_name' => $owner->name,
            ]);
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage());
        }

        return Response::text(
            "Your site is live at {$site->url()} — open it on screen rather than reading the address aloud. "
            ."Manage it at {$this->manageUrl($site, $token)}"
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function update(Owner $owner, array $validated, SiteComposer $composer): Response
    {
        $slug = Site::slugFromAddress((string) $validated['site']);
        $site = $slug !== null
            ? $owner->sites()->where('slug', $slug)->first()
            : null;

        if ($site === null) {
            return Response::error('No site of yours at that address. Pass the URL you were given when it was made, or leave site out to make a new one.');
        }

        $composer->regenerate($site, $validated);

        return Response::text("Updated. Same link: {$site->url()} — it refreshes on its own if it is open.");
    }

    protected function owner(): ?Owner
    {
        $user = auth()->user();

        if ($user === null || ! isset($user->email)) {
            return null;
        }

        return Owner::query()->firstOrCreate(
            ['email' => Str::lower($user->email)],
            ['name' => $user->name ?? null],
        );
    }

    protected function manageUrl(Site $site, string $token): string
    {
        return route('microsite.manage', $site->slug).'?t='.$token;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'site' => $schema->string()
                ->description('To change a site you already made: its URL, or the last part of it. Leave out to make a new site.'),
            'business_name' => $schema->string()
                ->description('The business or idea name, plain text, up to 80 characters. Needed for a new site.'),
            'prompt' => $schema->string()
                ->description('One or two plain sentences on what the promo is and who it is for. Needed for a new site.'),
            'type' => $schema->string()
                ->enum([Site::TYPE_STANDARD, Site::TYPE_SWEEPSTAKES])
                ->description('standard for a promo/landing page, sweepstakes for a giveaway. Defaults to standard.'),
            'accent_color' => $schema->string()
                ->description('Optional hex colour like #0f766e that suits the business.'),
            'hero_image' => $schema->string()
                ->enum(['auto', 'none', ...HeroArt::ILLUSTRATIONS])
                ->description('Optional hero drawing; "auto" lets the tool choose one that fits.'),
            'offer_headline' => $schema->string()->description('Optional custom headline.'),
            'cta_label' => $schema->string()->description('Optional button text.'),
            'location' => $schema->string()->description('Optional location line.'),
            'contact_phone' => $schema->string()->description('Optional contact phone.'),
            'prize' => $schema->string()->description('Sweepstakes: what can be won (products or swag only). Needed for a new sweepstakes.'),
            'prize_value' => $schema->string()->description('Sweepstakes: approximate prize value, e.g. $250. Keep it modest.'),
            'start_date' => $schema->string()->description('Sweepstakes: start date (YYYY-MM-DD).'),
            'end_date' => $schema->string()->description('Sweepstakes: end date (YYYY-MM-DD).'),
            'eligibility' => $schema->string()->description('Sweepstakes: who may enter, e.g. New York residents 18+.'),
        ];
    }
}
