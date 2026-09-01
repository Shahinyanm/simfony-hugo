<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Organization;
use App\Message\CredentialsStateChanged;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class TopupRecoveryTest extends ApiTestCase
{
    private function outbox(): InMemoryTransport
    {
        return static::getContainer()->get('messenger.transport.outbox');
    }

    /**
     * @return array{Organization, string}
     */
    private function exhaustedOrganization(): array
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $credentialId = $this->createCredential($organization)->getId()->toRfc4122();

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 1_500_000,
            'event_id' => 'evt-burn',
        ]);

        $this->jsonRequest('GET', '/api/credentials/'.$credentialId, $organization->getApiKey());
        self::assertFalse($this->jsonResponse()['enabled']);

        return [$organization, $credentialId];
    }

    #[Test]
    public function sufficientTopupReenablesCredentialsAndNotifiesBroker(): void
    {
        [$organization, $credentialId] = $this->exhaustedOrganization();

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/topups', $organization->getApiKey(), [
            'bytes' => 1_000_000,
        ]);
        self::assertResponseIsSuccessful();

        $sent = $this->outbox()->getSent();
        self::assertCount(1, $sent);

        $event = $sent[0]->getMessage();
        self::assertInstanceOf(CredentialsStateChanged::class, $event);
        self::assertTrue($event->enabled);
        self::assertSame('quota_restored', $event->reason);

        $this->jsonRequest('GET', '/api/credentials/'.$credentialId, $organization->getApiKey());
        self::assertTrue($this->jsonResponse()['enabled']);
    }

    #[Test]
    public function insufficientTopupKeepsCredentialsDisabledAndSendsNothing(): void
    {
        [$organization, $credentialId] = $this->exhaustedOrganization();

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/topups', $organization->getApiKey(), [
            'bytes' => 100_000,
        ]);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $this->outbox()->getSent());

        $this->jsonRequest('GET', '/api/credentials/'.$credentialId, $organization->getApiKey());
        self::assertFalse($this->jsonResponse()['enabled']);
    }

    #[Test]
    public function topupResponseReflectsNewQuota(): void
    {
        [$organization] = $this->exhaustedOrganization();

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/topups', $organization->getApiKey(), [
            'bytes' => 1_000_000,
        ]);

        $body = $this->jsonResponse();
        self::assertSame(2_000_000, $body['quota_bytes']);
        self::assertSame(1_500_000, $body['consumption_bytes']);
        self::assertSame(500_000, $body['remaining_bytes']);
    }
}
