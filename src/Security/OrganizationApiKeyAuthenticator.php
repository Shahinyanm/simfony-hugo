<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final readonly class OrganizationApiKeyAuthenticator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function requireFromApiKey(Request $request): Organization
    {
        $apiKey = $request->headers->get('X-Api-Key');
        if (null === $apiKey || '' === $apiKey) {
            throw new UnauthorizedHttpException('ApiKey', 'Missing X-Api-Key header.');
        }

        $organization = $this->entityManager->getRepository(Organization::class)->findOneBy(['apiKey' => $apiKey]);
        if (null === $organization) {
            throw new UnauthorizedHttpException('ApiKey', 'Invalid API key.');
        }

        return $organization;
    }

    public function requireOrganization(Request $request, string $organizationId): Organization
    {
        $organization = $this->requireFromApiKey($request);
        if ($organization->getId()->toRfc4122() !== $organizationId) {
            throw new AccessDeniedHttpException('API key does not match this organization.');
        }

        return $organization;
    }
}
