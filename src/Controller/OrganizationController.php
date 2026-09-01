<?php

declare(strict_types=1);

namespace App\Controller;

use App\Application\Topup\TopupApplier;
use App\Entity\Organization;
use App\Message\IngestUsageBatchMessage;
use App\Message\IngestUsageMessage;
use App\Security\OrganizationApiKeyAuthenticator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class OrganizationController
{
    private const int EVENT_ID_MAX_LENGTH = 128;
    private const int BATCH_MAX_EVENTS = 1000;

    public function __construct(
        private readonly OrganizationApiKeyAuthenticator $authenticator,
        private readonly MessageBusInterface $messageBus,
        private readonly TopupApplier $topupApplier,
    ) {
    }

    #[Route(path: '/api/organizations/{id}', name: 'organization_get', methods: ['GET'])]
    public function show(Request $request, string $id): JsonResponse
    {
        $organization = $this->authenticator->requireOrganization($request, $id);

        return new JsonResponse($this->organizationPayload($organization));
    }

    #[Route(path: '/api/organizations/{id}/usage', name: 'organization_post_usage', methods: ['POST'])]
    public function ingestUsage(Request $request, string $id): JsonResponse
    {
        $this->authenticator->requireOrganization($request, $id);

        $payload = $this->decodePayload($request);
        $bytes = $this->requirePositiveInt($payload, 'bytes');
        $eventId = $this->requireNonEmptyString($payload, 'event_id');

        $this->messageBus->dispatch(new IngestUsageMessage($id, $bytes, $eventId));

        return new JsonResponse(['status' => 'accepted'], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/organizations/{id}/usage/batch', name: 'organization_post_usage_batch', methods: ['POST'])]
    public function ingestUsageBatch(Request $request, string $id): JsonResponse
    {
        $this->authenticator->requireOrganization($request, $id);

        $payload = $this->decodePayload($request);
        $events = $payload['events'] ?? null;

        if (!\is_array($events) || [] === $events || !\array_is_list($events)) {
            throw new BadRequestHttpException('"events" must be a non-empty list.');
        }

        if (\count($events) > self::BATCH_MAX_EVENTS) {
            throw new BadRequestHttpException(\sprintf('"events" must not exceed %d entries.', self::BATCH_MAX_EVENTS));
        }

        $normalized = [];
        foreach ($events as $event) {
            if (!\is_array($event)) {
                throw new BadRequestHttpException('Each entry of "events" must be an object.');
            }

            $normalized[] = [
                'bytes' => $this->requirePositiveInt($event, 'bytes'),
                'event_id' => $this->requireNonEmptyString($event, 'event_id'),
            ];
        }

        $this->messageBus->dispatch(new IngestUsageBatchMessage($id, $normalized));

        return new JsonResponse(['status' => 'accepted', 'count' => \count($normalized)], Response::HTTP_ACCEPTED);
    }

    #[Route(path: '/api/organizations/{id}/topups', name: 'organization_post_topup', methods: ['POST'])]
    public function topup(Request $request, string $id): JsonResponse
    {
        $this->authenticator->requireOrganization($request, $id);
        $payload = $this->decodePayload($request);
        $bytes = $this->requirePositiveInt($payload, 'bytes');

        return new JsonResponse($this->organizationPayload($this->topupApplier->apply($id, $bytes)));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        try {
            $payload = \json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('Invalid JSON body.', $exception);
        }

        if (!\is_array($payload)) {
            throw new BadRequestHttpException('JSON body must be an object.');
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requirePositiveInt(array $payload, string $field): int
    {
        $value = $payload[$field] ?? null;
        if (\is_string($value) && \ctype_digit($value)) {
            $value = (int) $value;
        }
        if (!\is_int($value) || $value < 1) {
            throw new BadRequestHttpException(\sprintf('"%s" must be a positive integer.', $field));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requireNonEmptyString(array $payload, string $field, int $maxLength = self::EVENT_ID_MAX_LENGTH): string
    {
        $value = $payload[$field] ?? null;
        if (!\is_string($value) || '' === \trim($value)) {
            throw new BadRequestHttpException(\sprintf('"%s" is required.', $field));
        }

        if (\strlen($value) > $maxLength) {
            throw new BadRequestHttpException(\sprintf('"%s" must not exceed %d characters.', $field, $maxLength));
        }

        return $value;
    }

    /**
     * @return array{id: string, name: string, quota_bytes: int, consumption_bytes: int, remaining_bytes: int}
     */
    private function organizationPayload(Organization $organization): array
    {
        return [
            'id' => $organization->getId()->toRfc4122(),
            'name' => $organization->getName(),
            'quota_bytes' => $organization->getQuotaBytes(),
            'consumption_bytes' => $organization->getConsumptionBytes(),
            'remaining_bytes' => $organization->getRemainingBytes(),
        ];
    }
}
