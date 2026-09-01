<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CredentialsStateChanged;
use App\Message\ProxyFleetNotification;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RelayCredentialsStateChangedHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CredentialsStateChanged $event): void
    {
        $this->messageBus->dispatch(ProxyFleetNotification::fromDomainEvent($event));

        $this->logger->info('proxy fleet notified', [
            'organization_id' => $event->organizationId,
            'enabled' => $event->enabled,
            'credential_ids' => $event->credentialIds,
            'reason' => $event->reason,
        ]);
    }
}
