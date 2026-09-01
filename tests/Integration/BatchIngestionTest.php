<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\CredentialsStateChanged;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

final class BatchIngestionTest extends ApiTestCase
{
    /**
     * @param list<array{bytes: int, event_id: string}> $events
     */
    private function postBatch(string $organizationId, string $apiKey, array $events): void
    {
        $this->jsonRequest('POST', '/api/organizations/'.$organizationId.'/usage/batch', $apiKey, ['events' => $events]);
    }

    #[Test]
    public function batchAppliesAllNewEvents(): void
    {
        $organization = $this->createOrganization(quotaBytes: 10_000_000);
        $id = $organization->getId()->toRfc4122();

        $this->postBatch($id, $organization->getApiKey(), [
            ['bytes' => 1_000_000, 'event_id' => 'evt-1'],
            ['bytes' => 2_000_000, 'event_id' => 'evt-2'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->jsonRequest('GET', '/api/organizations/'.$id, $organization->getApiKey());
        self::assertSame(3_000_000, $this->jsonResponse()['consumption_bytes']);
    }

    #[Test]
    public function partiallyDuplicatedBatchAppliesOnlyNewEvents(): void
    {
        $organization = $this->createOrganization(quotaBytes: 10_000_000);
        $id = $organization->getId()->toRfc4122();

        $this->postBatch($id, $organization->getApiKey(), [
            ['bytes' => 1_000_000, 'event_id' => 'evt-1'],
        ]);

        $this->postBatch($id, $organization->getApiKey(), [
            ['bytes' => 1_000_000, 'event_id' => 'evt-1'],
            ['bytes' => 1_000_000, 'event_id' => 'evt-2'],
        ]);

        $this->jsonRequest('GET', '/api/organizations/'.$id, $organization->getApiKey());
        self::assertSame(2_000_000, $this->jsonResponse()['consumption_bytes']);
    }

    #[Test]
    public function duplicatesInsideOneBatchAreCollapsed(): void
    {
        $organization = $this->createOrganization(quotaBytes: 10_000_000);
        $id = $organization->getId()->toRfc4122();

        $this->postBatch($id, $organization->getApiKey(), [
            ['bytes' => 1_000_000, 'event_id' => 'evt-1'],
            ['bytes' => 1_000_000, 'event_id' => 'evt-1'],
        ]);

        $this->jsonRequest('GET', '/api/organizations/'.$id, $organization->getApiKey());
        self::assertSame(1_000_000, $this->jsonResponse()['consumption_bytes']);
    }

    #[Test]
    public function batchCrossingQuotaDisablesCredentialsWithOneNotification(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $credentialId = $this->createCredential($organization)->getId()->toRfc4122();
        $id = $organization->getId()->toRfc4122();

        $this->postBatch($id, $organization->getApiKey(), [
            ['bytes' => 400_000, 'event_id' => 'evt-1'],
            ['bytes' => 400_000, 'event_id' => 'evt-2'],
            ['bytes' => 400_000, 'event_id' => 'evt-3'],
        ]);

        self::assertCount(1, $this->sentToOutbox(CredentialsStateChanged::class));

        $this->jsonRequest('GET', '/api/credentials/'.$credentialId, $organization->getApiKey());
        self::assertFalse($this->jsonResponse()['enabled']);
    }

    #[Test]
    public function eventsAfterTheQuotaIsGoneAreRejectedWithinTheSameBatch(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $this->createCredential($organization);
        $id = $organization->getId()->toRfc4122();

        $this->postBatch($id, $organization->getApiKey(), [
            ['bytes' => 1_000_000, 'event_id' => 'evt-1'],
            ['bytes' => 500_000, 'event_id' => 'evt-2'],
        ]);

        $this->jsonRequest('GET', '/api/organizations/'.$id, $organization->getApiKey());
        self::assertSame(1_000_000, $this->jsonResponse()['consumption_bytes']);
    }

    #[Test]
    public function emptyBatchIsRejected(): void
    {
        $organization = $this->createOrganization();

        $this->postBatch($organization->getId()->toRfc4122(), $organization->getApiKey(), []);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    #[Test]
    public function oversizedBatchIsRejected(): void
    {
        $organization = $this->createOrganization();

        $events = [];
        for ($i = 0; $i <= 1_000; ++$i) {
            $events[] = ['bytes' => 1, 'event_id' => 'evt-'.$i];
        }

        $this->postBatch($organization->getId()->toRfc4122(), $organization->getApiKey(), $events);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    #[Test]
    public function malformedEventInBatchRejectsTheWholeRequest(): void
    {
        $organization = $this->createOrganization();
        $id = $organization->getId()->toRfc4122();

        $this->postBatch($id, $organization->getApiKey(), [
            ['bytes' => 1_000, 'event_id' => 'evt-1'],
            ['bytes' => 0, 'event_id' => 'evt-2'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->jsonRequest('GET', '/api/organizations/'.$id, $organization->getApiKey());
        self::assertSame(0, $this->jsonResponse()['consumption_bytes']);
    }
}
