<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProxyFleetNotification
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

    public static function fromDomainEvent(CredentialsStateChanged $event): self
    {
        return new self(
            organizationId: $event->organizationId,
            credentialIds: $event->credentialIds,
            enabled: $event->enabled,
            reason: $event->reason,
            occurredAt: $event->occurredAt,
        );
    }
}
