<?php

namespace Tests\Feature;

use App\Support\EmailAuthenticity;
use Tests\TestCase;

/**
 * The near-free pre-send authenticity checks: disposable-domain blocklist and
 * the MX/A deliverability lookup.
 */
class EmailAuthenticityTest extends TestCase
{
    public function test_it_flags_disposable_domains_including_subdomains(): void
    {
        $this->assertTrue(EmailAuthenticity::isDisposable('bot@mailinator.com'));
        $this->assertTrue(EmailAuthenticity::isDisposable('bot@inbox.mailinator.com'));
        $this->assertTrue(EmailAuthenticity::isDisposable('a@GuerrillaMail.com'));
    }

    public function test_it_allows_real_domains(): void
    {
        $this->assertFalse(EmailAuthenticity::isDisposable('jamie@gmail.com'));
        $this->assertFalse(EmailAuthenticity::isDisposable('jamie@thelocalhostcompany.com'));
        $this->assertFalse(EmailAuthenticity::isDisposable('not-an-email'));
    }

    public function test_mail_server_check_uses_the_resolver(): void
    {
        $this->assertTrue(EmailAuthenticity::hasMailServer('jamie@example.com', fn () => true));
        $this->assertFalse(EmailAuthenticity::hasMailServer('jamie@no-mail.example', fn () => false));
    }

    public function test_mail_server_check_fails_open_on_dns_error(): void
    {
        $this->assertTrue(EmailAuthenticity::hasMailServer('jamie@example.com', function (): bool {
            throw new \RuntimeException('DNS down');
        }));
    }

    public function test_a_garbled_address_has_no_mail_server(): void
    {
        $this->assertFalse(EmailAuthenticity::hasMailServer('no-at-sign', fn () => true));
    }
}
