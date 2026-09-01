<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Quota\CredentialStateReason;
use App\Domain\Quota\QuotaGuard;
use App\Entity\Credential;
use App\Entity\Organization;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class QuotaGuardTest extends TestCase
{
    private function guard(): QuotaGuard
    {
        return new QuotaGuard(new MockClock('2026-09-01 12:00:00'));
    }

    private function organization(int $quotaBytes, int $consumptionBytes, int $credentials = 2): Organization
    {
        $organization = new Organization('Acme', 'key-'.\bin2hex(\random_bytes(4)), $quotaBytes);

        for ($i = 1; $i <= $credentials; ++$i) {
            new Credential($organization, 'proxy-'.$i);
        }

        if ($consumptionBytes > 0) {
            $organization->addConsumption($consumptionBytes);
        }

        return $organization;
    }

    #[Test]
    public function disablesAllCredentialsWhenOverQuota(): void
    {
        $organization = $this->organization(quotaBytes: 1_000, consumptionBytes: 1_500);

        $change = $this->guard()->synchronize($organization);

        self::assertNotNull($change);
        self::assertFalse($change->enabled);
        self::assertSame(CredentialStateReason::QuotaExhausted, $change->reason);
        self::assertCount(2, $change->credentialIds);

        foreach ($organization->getCredentials() as $credential) {
            self::assertFalse($credential->isEnabled());
        }
    }

    #[Test]
    public function treatsExactQuotaAsOverQuota(): void
    {
        $organization = $this->organization(quotaBytes: 1_000, consumptionBytes: 1_000);

        $change = $this->guard()->synchronize($organization);

        self::assertNotNull($change);
        self::assertFalse($change->enabled);
    }

    #[Test]
    public function enablesAllCredentialsWhenBackUnderQuota(): void
    {
        $organization = $this->organization(quotaBytes: 1_000, consumptionBytes: 1_500);
        $this->guard()->synchronize($organization);

        $organization->addQuota(1_000);
        $change = $this->guard()->synchronize($organization);

        self::assertNotNull($change);
        self::assertTrue($change->enabled);
        self::assertSame(CredentialStateReason::QuotaRestored, $change->reason);

        foreach ($organization->getCredentials() as $credential) {
            self::assertTrue($credential->isEnabled());
        }
    }

    #[Test]
    public function returnsNullWhenStateAlreadyCorrect(): void
    {
        $organization = $this->organization(quotaBytes: 1_000, consumptionBytes: 100);

        self::assertNull($this->guard()->synchronize($organization));
    }

    #[Test]
    public function isIdempotentOnRepeatedCalls(): void
    {
        $organization = $this->organization(quotaBytes: 1_000, consumptionBytes: 1_500);

        self::assertNotNull($this->guard()->synchronize($organization));
        self::assertNull($this->guard()->synchronize($organization));
    }

    #[Test]
    public function reportsOnlyCredentialsThatActuallyChanged(): void
    {
        $organization = $this->organization(quotaBytes: 1_000, consumptionBytes: 1_500);
        $organization->getCredentials()->first()->setEnabled(false);

        $change = $this->guard()->synchronize($organization);

        self::assertNotNull($change);
        self::assertCount(1, $change->credentialIds);
    }

    #[Test]
    public function recordsWhenTheChangeHappened(): void
    {
        $organization = $this->organization(quotaBytes: 1_000, consumptionBytes: 1_500);

        $change = $this->guard()->synchronize($organization);

        self::assertNotNull($change);
        self::assertSame('2026-09-01T12:00:00+00:00', $change->occurredAt->format(\DATE_ATOM));
    }
}
