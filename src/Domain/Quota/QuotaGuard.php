<?php

declare(strict_types=1);

namespace App\Domain\Quota;

use App\Entity\Organization;
use Psr\Clock\ClockInterface;

final readonly class QuotaGuard
{
    public function __construct(
        private ClockInterface $clock,
    ) {
    }

    public function synchronize(Organization $organization): ?CredentialStateChange
    {
        $shouldBeEnabled = !$organization->isOverQuota();

        $affected = [];
        foreach ($organization->getCredentials() as $credential) {
            if ($credential->isEnabled() === $shouldBeEnabled) {
                continue;
            }

            $credential->setEnabled($shouldBeEnabled);
            $affected[] = $credential->getId()->toRfc4122();
        }

        if ([] === $affected) {
            return null;
        }

        return new CredentialStateChange(
            organizationId: $organization->getId()->toRfc4122(),
            credentialIds: $affected,
            enabled: $shouldBeEnabled,
            reason: $shouldBeEnabled
                ? CredentialStateReason::QuotaRestored
                : CredentialStateReason::QuotaExhausted,
            occurredAt: $this->clock->now(),
        );
    }
}
