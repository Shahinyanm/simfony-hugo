<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\QuotaThresholdNotification;
use App\Message\QuotaThresholdReached;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RelayQuotaThresholdReachedHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(QuotaThresholdReached $event): void
    {
        $this->messageBus->dispatch(QuotaThresholdNotification::fromDomainEvent($event));

        $this->logger->info('quota threshold notified', [
            'organization_id' => $event->organizationId,
            'threshold_percent' => $event->thresholdPercent,
        ]);
    }
}
