<?php

use App\Mcp\Servers\MicrositesServer;
use Laravel\Mcp\Facades\Mcp;

/*
 * OAuth 2.1 discovery + dynamic client registration. An MCP client (e.g. Grok
 * added as a custom connector in a Tesla) hits these to find the authorization
 * server and register itself before the authorization-code flow. Backed by
 * Passport; advertises the `mcp:use` scope.
 */
Mcp::oauthRoutes();

/*
 * The connector endpoint. Each connected account owns the sites it creates.
 */
Mcp::web('/mcp', MicrositesServer::class)
    ->middleware('auth:api');
