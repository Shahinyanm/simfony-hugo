<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Organization;
use App\Message\CredentialsStateChanged;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

final class QuotaCutOffTest extends ApiTestCase
{
    #[Test]
    public function exhaustingQuotaDisablesAllCredentials(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $this->createCredential($organization, 'proxy-1');
        $this->createCredential($organization, 'proxy-2');

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 1_000_000,
            'event_id' => 'evt-burn',
        ]);

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Organization::class, $organization->getId());

        self::assertTrue($reloaded->isOverQuota());
        foreach ($reloaded->getCredentials() as $credential) {
            self::assertFalse($credential->isEnabled());
        }
    }

    #[Test]
    public function exhaustingQuotaDispatchesBrokerNotification(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $this->createCredential($organization, 'proxy-1');
        $this->createCredential($organization, 'proxy-2');

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 1_500_000,
            'event_id' => 'evt-burn',
        ]);

        $messages = $this->sentToOutbox(CredentialsStateChanged::class);
        self::assertCount(1, $messages);

        $event = $messages[0];
        self::assertFalse($event->enabled);
        self::assertSame('quota_exhausted', $event->reason);
        self::assertCount(2, $event->credentialIds);
        self::assertSame($organization->getId()->toRfc4122(), $event->organizationId);
    }

    #[Test]
    public function usageBelowQuotaKeepsCredentialsEnabledAndSendsNothing(): void
    {
        $organization = $this->createOrganization(quotaBytes: 10_000_000);
        $credential = $this->createCredential($organization);

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 1_000_000,
            'event_id' => 'evt-1',
        ]);

        $this->entityManager->refresh($credential);
        self::assertTrue($credential->isEnabled());
        self::assertCount(0, $this->sentToOutbox(CredentialsStateChanged::class));
    }

    #[Test]
    public function furtherUsageIsRejectedAndCredentialsStayDisabled(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $credentialId = $this->createCredential($organization)->getId()->toRfc4122();
        $uri = '/api/organizations/'.$organization->getId()->toRfc4122().'/usage';

        $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 1_000_000, 'event_id' => 'evt-burn']);
        self::assertCount(1, $this->sentToOutbox(CredentialsStateChanged::class));

        $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 500_000, 'event_id' => 'evt-after']);
        self::assertCount(0, $this->sentToOutbox(CredentialsStateChanged::class));

        $this->jsonRequest('GET', '/api/organizations/'.$organization->getId()->toRfc4122(), $organization->getApiKey());
        self::assertSame(1_000_000, $this->jsonResponse()['consumption_bytes']);

        $this->jsonRequest('GET', '/api/credentials/'.$credentialId, $organization->getApiKey());
        self::assertFalse($this->jsonResponse()['enabled']);
    }

    #[Test]
    public function credentialAddedWhileOverQuotaIsBornDisabled(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $this->createCredential($organization, 'proxy-1');

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 1_500_000,
            'event_id' => 'evt-burn',
        ]);

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Organization::class, $organization->getId());
        $late = $this->createCredential($reloaded, 'proxy-late');

        self::assertFalse($late->isEnabled());
    }
}
