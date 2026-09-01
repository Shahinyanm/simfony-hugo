<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Quota\CredentialStateReason;
use App\Message\CredentialsStateChanged;
use App\Message\ProxyFleetNotification;
use App\MessageHandler\RelayCredentialsStateChangedHandler;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class OutboxRelayTest extends ApiTestCase
{
    #[Test]
    public function relayPublishesDomainEventToBrokerTransport(): void
    {
        $event = new CredentialsStateChanged(
            organizationId: '01a05bb3-4b6e-71a3-afc3-54ef29acd8ba',
            credentialIds: ['01a05bb3-4b6e-7203-afc3-54ef29c56c86'],
            enabled: false,
            reason: CredentialStateReason::QuotaExhausted->value,
            occurredAt: '2026-09-01T12:00:00+00:00',
        );

        $handler = static::getContainer()->get(RelayCredentialsStateChangedHandler::class);
        self::assertInstanceOf(RelayCredentialsStateChangedHandler::class, $handler);
        $handler($event);

        $broker = static::getContainer()->get('messenger.transport.broker');
        self::assertInstanceOf(InMemoryTransport::class, $broker);

        $sent = $broker->getSent();

        self::assertCount(1, $sent);

        $notification = $sent[0]->getMessage();
        self::assertInstanceOf(ProxyFleetNotification::class, $notification);
        self::assertSame($event->organizationId, $notification->organizationId);
        self::assertSame($event->credentialIds, $notification->credentialIds);
        self::assertFalse($notification->enabled);
        self::assertSame('quota_exhausted', $notification->reason);
        self::assertSame('2026-09-01T12:00:00+00:00', $notification->occurredAt);
    }

    #[Test]
    public function notificationCarriesTargetStateNotDelta(): void
    {
        $notification = ProxyFleetNotification::fromDomainEvent(new CredentialsStateChanged(
            organizationId: 'org-1',
            credentialIds: ['cred-1'],
            enabled: true,
            reason: CredentialStateReason::QuotaRestored->value,
            occurredAt: '2026-09-01T12:00:00+00:00',
        ));

        self::assertTrue($notification->enabled);
        self::assertSame('2026-09-01T12:00:00+00:00', $notification->occurredAt);
    }
}
