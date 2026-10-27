<?php

namespace Tests\Feature;

use App\Support\EntryCode;
use Tests\TestCase;

/**
 * The cache-backed one-time code service that backs email verification.
 */
class EntryCodeTest extends TestCase
{
    public function test_a_code_verifies_once_then_is_burned(): void
    {
        $code = EntryCode::issue(1, 'a@example.com');

        $this->assertNotNull($code);
        $this->assertSame(6, strlen($code));
        $this->assertTrue(EntryCode::check(1, 'a@example.com', $code));
        $this->assertFalse(EntryCode::check(1, 'a@example.com', $code));
    }

    public function test_a_wrong_code_burns_after_the_attempt_limit(): void
    {
        $code = EntryCode::issue(1, 'a@example.com');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < EntryCode::MAX_ATTEMPTS; $i++) {
            $this->assertFalse(EntryCode::check(1, 'a@example.com', $wrong));
        }

        // Burned: even the correct code no longer works.
        $this->assertFalse(EntryCode::check(1, 'a@example.com', $code));
    }

    public function test_a_code_expires(): void
    {
        $code = EntryCode::issue(1, 'a@example.com');

        $this->travel(EntryCode::TTL_MINUTES + 1)->minutes();

        $this->assertFalse(EntryCode::check(1, 'a@example.com', $code));
    }

    public function test_a_fresh_code_is_not_reissued_within_the_resend_window(): void
    {
        $this->assertNotNull(EntryCode::issue(1, 'a@example.com'));
        $this->assertNull(EntryCode::issue(1, 'a@example.com'));

        $this->travel(EntryCode::RESEND_SECONDS + 1)->seconds();

        $this->assertNotNull(EntryCode::issue(1, 'a@example.com'));
    }

    public function test_codes_are_scoped_to_the_site(): void
    {
        $code = EntryCode::issue(1, 'a@example.com');

        // The same email on a different site has no code of its own.
        $this->assertFalse(EntryCode::check(2, 'a@example.com', $code));
        $this->assertTrue(EntryCode::check(1, 'a@example.com', $code));
    }
}
