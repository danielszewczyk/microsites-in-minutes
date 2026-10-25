<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\MakeMicrositeTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Microsites in Minutes')]
#[Version('0.1.0')]
#[Instructions(<<<'TXT'
Microsites in Minutes publishes a polished one-page promo or sweepstakes
microsite from a short prompt and returns a live, shareable URL — built for
hands-free use, for example a Tesla owner talking to Grok in the car.

make-microsite is the only tool. To make a site, give a business_name and a
prompt (one or two sentences on what the promo is and who it is for); it writes
the page, publishes it and returns the live URL. To change a site, pass its URL
back as `site` with only the fields to change; the same link updates and any
open page refreshes itself. Never read a URL aloud — say the site is live, and
open it on screen if you can.

For a giveaway, set type to "sweepstakes" and give a prize; the tool writes the
official rules and a free alternate method of entry. Keep prizes modest
(products or swag, never free or discounted services) and limit who may enter to
one state to keep it simple. Each connected account owns the sites it creates.
TXT)]
class MicrositesServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        MakeMicrositeTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        //
    ];

    /**
     * @var array<int, class-string<Server\Prompt>>
     */
    protected array $prompts = [
        //
    ];
}
