<?php

namespace Tests\Feature;

use App\Mcp\Servers\MicrositesServer;
use App\Mcp\Tools\MakeMicrositeTool;
use App\Models\Owner;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_connected_account_can_make_a_microsite(): void
    {
        $user = Owner::factory()->create(['email' => 'tesla@example.com', 'name' => 'Tesla Owner']);

        MicrositesServer::actingAs($user, 'api')
            ->tool(MakeMicrositeTool::class, [
                'business_name' => "Paul's Parlor",
                'prompt' => 'A friendly neighbourhood barber that wants more weekday walk-ins this month.',
                'type' => 'standard',
            ])
            ->assertOk()
            ->assertSee('is live at');

        $owner = Owner::query()->where('email', 'tesla@example.com')->firstOrFail();
        $this->assertSame(1, $owner->sites()->count());
    }

    public function test_sweepstakes_through_the_connector_gets_rules(): void
    {
        $user = Owner::factory()->create(['email' => 'tesla@example.com']);

        MicrositesServer::actingAs($user, 'api')
            ->tool(MakeMicrositeTool::class, [
                'business_name' => 'Giveaway Co',
                'prompt' => 'A grand-opening giveaway for our new shop downtown this month.',
                'type' => 'sweepstakes',
                'prize' => 'a gift basket',
                'prize_value' => '$150',
                'eligibility' => 'New York residents 18+',
            ])
            ->assertOk();

        $site = Site::query()->firstOrFail();
        $this->assertTrue($site->isSweepstakes());
        $this->assertIsArray($site->content['rules']);
        $this->assertStringContainsString('NO PURCHASE NECESSARY', $site->content['rules']['no_purchase']);
    }

    public function test_a_new_sweepstakes_requires_a_prize(): void
    {
        $user = Owner::factory()->create();

        MicrositesServer::actingAs($user, 'api')
            ->tool(MakeMicrositeTool::class, [
                'business_name' => 'Giveaway Co',
                'prompt' => 'A giveaway with no prize described, which the tool should reject up front.',
                'type' => 'sweepstakes',
            ])
            ->assertHasErrors();
    }

    public function test_an_account_can_only_change_its_own_site(): void
    {
        $mine = Owner::factory()->create(['email' => 'mine@example.com']);
        $someoneElse = Site::factory()->create();

        MicrositesServer::actingAs($mine, 'api')
            ->tool(MakeMicrositeTool::class, [
                'site' => $someoneElse->slug,
                'prompt' => 'An attacker trying to rewrite a site owned by a different account entirely.',
            ])
            ->assertSee('No site of yours at that address');
    }
}
