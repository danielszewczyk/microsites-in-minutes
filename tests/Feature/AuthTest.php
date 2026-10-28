<?php

namespace Tests\Feature;

use App\Mcp\Servers\MicrositesServer;
use App\Mcp\Tools\MakeMicrositeTool;
use App\Models\Owner;
use App\Models\Site;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_hashed_unverified_owner_and_sends_verification(): void
    {
        Notification::fake();

        $this->post('/register', [
            'email' => 'Pat@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('verification.notice'));

        $owner = Owner::query()->firstOrFail();
        $this->assertSame('pat@example.com', $owner->email);
        $this->assertNotSame('password123', $owner->password);
        $this->assertTrue(Hash::check('password123', $owner->password));
        $this->assertNull($owner->email_verified_at);
        $this->assertAuthenticatedAs($owner);
        Notification::assertSentTo($owner, VerifyEmail::class);
    }

    public function test_registration_rejects_a_duplicate_email_case_insensitively(): void
    {
        Owner::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'email' => 'TAKEN@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, Owner::query()->count());
    }

    public function test_login_and_logout(): void
    {
        $owner = Owner::factory()->create(['email' => 'pat@example.com']);

        $this->post('/login', ['email' => 'nope@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'PAT@example.com', 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($owner);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_email_verification_link_verifies_the_account(): void
    {
        $owner = Owner::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $owner->id, 'hash' => sha1($owner->email)],
        );

        $this->actingAs($owner)->get($url)->assertRedirect(route('home'));
        $this->assertNotNull($owner->fresh()->email_verified_at);
    }

    public function test_an_unverified_owner_cannot_publish_over_mcp(): void
    {
        $owner = Owner::factory()->unverified()->create();

        MicrositesServer::actingAs($owner, 'api')
            ->tool(MakeMicrositeTool::class, [
                'business_name' => 'Sneaky Co',
                'prompt' => 'Trying to publish without verifying the account email first here.',
                'type' => 'standard',
            ])
            ->assertSee('verify your email');

        $this->assertSame(0, Site::query()->count());
    }

    public function test_the_mcp_generator_endpoint_rejects_an_unauthenticated_request(): void
    {
        // Generation has no anonymous path: the MCP endpoint is behind
        // auth:api + verified, so with no bearer token at all it is rejected
        // before any tool runs (the web POST /sites gate is covered in BuilderTest).
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'make_microsite', 'arguments' => []],
        ])->assertUnauthorized();

        $this->assertSame(0, Site::query()->count());
    }
}
