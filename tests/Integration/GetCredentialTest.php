<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\Support\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

final class GetCredentialTest extends ApiTestCase
{
    #[Test]
    public function itReturnsCredentialStatus(): void
    {
        $organization = $this->createOrganization();
        $credential = $this->createCredential($organization, 'acme-proxy-1');

        $this->jsonRequest('GET', '/api/credentials/'.$credential->getId()->toRfc4122(), $organization->getApiKey());

        self::assertResponseIsSuccessful();
        $body = $this->jsonResponse();
        self::assertSame($credential->getId()->toRfc4122(), $body['id']);
        self::assertSame('acme-proxy-1', $body['username']);
        self::assertTrue($body['enabled']);
        self::assertSame($organization->getId()->toRfc4122(), $body['organization_id']);
    }

    #[Test]
    public function itRejectsAccessToAnotherOrganizationCredential(): void
    {
        $organization = $this->createOrganization();
        $credential = $this->createCredential($organization);
        $other = $this->createOrganization(name: 'Other');

        $this->jsonRequest('GET', '/api/credentials/'.$credential->getId()->toRfc4122(), $other->getApiKey());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
