<?php

declare(strict_types=1);

namespace App\Domain\Quota;

final readonly class CredentialStateChange
{
    /**
     * @param list<string> $credentialIds
     */
    public function __construct(
        public string $organizationId,
        public array $credentialIds,
        public bool $enabled,
        public CredentialStateReason $reason,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
