<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\CredentialsStateChanged;
use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

final class IdempotentIngestionTest extends ApiTestCase
{
    #[Test]
    public function sameEventIdIsAppliedOnlyOnce(): void
    {
        $organization = $this->createOrganization(quotaBytes: 10_000_000);
        $this->createCredential($organization);
        $uri = '/api/organizations/'.$organization->getId()->toRfc4122().'/usage';

        $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 1_000_000, 'event_id' => 'evt-1']);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 1_000_000, 'event_id' => 'evt-1']);
        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $this->jsonRequest('GET', '/api/organizations/'.$organization->getId()->toRfc4122(), $organization->getApiKey());
        self::assertSame(1_000_000, $this->jsonResponse()['consumption_bytes']);
    }

    #[Test]
    public function differentEventIdsWithSameBytesBothApply(): void
    {
        $organization = $this->createOrganization(quotaBytes: 10_000_000);
        $this->createCredential($organization);
        $uri = '/api/organizations/'.$organization->getId()->toRfc4122().'/usage';

        $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 1_000_000, 'event_id' => 'evt-1']);
        $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 1_000_000, 'event_id' => 'evt-2']);

        $this->jsonRequest('GET', '/api/organizations/'.$organization->getId()->toRfc4122(), $organization->getApiKey());
        self::assertSame(2_000_000, $this->jsonResponse()['consumption_bytes']);
    }

    #[Test]
    public function idempotencyIsScopedPerOrganization(): void
    {
        $first = $this->createOrganization(name: 'First', quotaBytes: 10_000_000);
        $second = $this->createOrganization(name: 'Second', quotaBytes: 10_000_000);

        $this->jsonRequest('POST', '/api/organizations/'.$first->getId()->toRfc4122().'/usage', $first->getApiKey(), ['bytes' => 1_000_000, 'event_id' => 'evt-shared']);
        $this->jsonRequest('POST', '/api/organizations/'.$second->getId()->toRfc4122().'/usage', $second->getApiKey(), ['bytes' => 1_000_000, 'event_id' => 'evt-shared']);

        $this->jsonRequest('GET', '/api/organizations/'.$second->getId()->toRfc4122(), $second->getApiKey());
        self::assertSame(1_000_000, $this->jsonResponse()['consumption_bytes']);
    }

    #[Test]
    public function redeliveryOfACutOffEventEmitsNoFurtherNotification(): void
    {
        $organization = $this->createOrganization(quotaBytes: 1_000_000);
        $this->createCredential($organization);
        $uri = '/api/organizations/'.$organization->getId()->toRfc4122().'/usage';

        $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 1_000_000, 'event_id' => 'evt-burn']);
        self::assertCount(1, $this->sentToOutbox(CredentialsStateChanged::class));

        $this->jsonRequest('POST', $uri, $organization->getApiKey(), ['bytes' => 1_000_000, 'event_id' => 'evt-burn']);
        self::assertCount(0, $this->sentToOutbox(CredentialsStateChanged::class));
    }

    #[Test]
    public function tooLongEventIdIsRejected(): void
    {
        $organization = $this->createOrganization();

        $this->jsonRequest('POST', '/api/organizations/'.$organization->getId()->toRfc4122().'/usage', $organization->getApiKey(), [
            'bytes' => 1_000,
            'event_id' => \str_repeat('x', 129),
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }
}
