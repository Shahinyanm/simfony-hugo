<?php

declare(strict_types=1);

namespace App\Message;

use App\Domain\Quota\CredentialStateChange;

final readonly class CredentialsStateChanged
{
    /**
     * @param list<string> $credentialIds
     */
    public function __construct(
        public string $organizationId,
        public array $credentialIds,
        public bool $enabled,
        public string $reason,
        public string $occurredAt,
    ) {
    }

    public static function fromChange(CredentialStateChange $change): self
    {
        return new self(
            organizationId: $change->organizationId,
            credentialIds: $change->credentialIds,
            enabled: $change->enabled,
            reason: $change->reason->value,
            occurredAt: $change->occurredAt->format(\DATE_ATOM),
        );
    }
}
