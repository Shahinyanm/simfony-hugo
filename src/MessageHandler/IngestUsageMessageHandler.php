<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Application\Usage\UsageApplier;
use App\Message\IngestUsageMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IngestUsageMessageHandler
{
    public function __construct(
        private UsageApplier $applier,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(IngestUsageMessage $message): void
    {
        $result = $this->applier->apply($message->organizationId, $message->eventId, $message->bytes);

        $this->logger->info('usage event processed', [
            'organization_id' => $message->organizationId,
            'event_id' => $message->eventId,
            'result' => $result->value,
        ]);
    }
}
