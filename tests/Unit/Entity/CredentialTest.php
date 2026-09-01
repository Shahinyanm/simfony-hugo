<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Credential;
use App\Entity\Organization;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CredentialTest extends TestCase
{
    #[Test]
    public function isCreatedEnabledWhenOrganizationHasQuotaLeft(): void
    {
        $organization = new Organization('Acme', 'key-under-quota', 1_000);

        self::assertTrue((new Credential($organization, 'proxy-1'))->isEnabled());
    }

    #[Test]
    public function isCreatedDisabledWhenOrganizationIsOverQuota(): void
    {
        $organization = new Organization('Acme', 'key-over-quota', 1_000);
        $organization->addConsumption(1_000);

        self::assertFalse((new Credential($organization, 'proxy-1'))->isEnabled());
    }
}
