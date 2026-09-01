<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Credential;
use App\Security\OrganizationApiKeyAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CredentialController
{
    public function __construct(
        private readonly OrganizationApiKeyAuthenticator $authenticator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/api/credentials/{id}', name: 'credential_get', methods: ['GET'])]
    public function show(Request $request, string $id): JsonResponse
    {
        $organization = $this->authenticator->requireFromApiKey($request);
        $credential = $this->entityManager->find(Credential::class, $id);
        if (null === $credential) {
            throw new NotFoundHttpException('Credential not found.');
        }
        if ($credential->getOrganization()->getId()->toRfc4122() !== $organization->getId()->toRfc4122()) {
            throw new AccessDeniedHttpException('Credential does not belong to this organization.');
        }

        return new JsonResponse([
            'id' => $credential->getId()->toRfc4122(),
            'username' => $credential->getUsername(),
            'enabled' => $credential->isEnabled(),
            'organization_id' => $credential->getOrganization()->getId()->toRfc4122(),
        ]);
    }
}
