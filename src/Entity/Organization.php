<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'organizations')]
class Organization
{
    public const int DEFAULT_QUOTA_BYTES = 10 * 1024 * 1024 * 1024;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(name: 'api_key', length: 64, unique: true)]
    private string $apiKey;

    #[ORM\Column(name: 'quota_bytes', type: Types::BIGINT)]
    private int $quotaBytes;

    #[ORM\Column(name: 'consumption_bytes', type: Types::BIGINT)]
    private int $consumptionBytes = 0;

    /**
     * @var Collection<int, Credential>
     */
    #[ORM\OneToMany(targetEntity: Credential::class, mappedBy: 'organization', cascade: ['persist'])]
    private Collection $credentials;

    public function __construct(string $name, string $apiKey, int $quotaBytes = self::DEFAULT_QUOTA_BYTES)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->apiKey = $apiKey;
        $this->quotaBytes = $quotaBytes;
        $this->credentials = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getQuotaBytes(): int
    {
        return (int) $this->quotaBytes;
    }

    public function getConsumptionBytes(): int
    {
        return (int) $this->consumptionBytes;
    }

    public function addConsumption(int $bytes): void
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('bytes must be greater than 0.');
        }

        $this->consumptionBytes = $this->getConsumptionBytes() + $bytes;
    }

    public function addQuota(int $bytes): void
    {
        if ($bytes < 1) {
            throw new \InvalidArgumentException('bytes must be greater than 0.');
        }

        $this->quotaBytes = $this->getQuotaBytes() + $bytes;
    }

    public function getRemainingBytes(): int
    {
        return \max(0, $this->getQuotaBytes() - $this->getConsumptionBytes());
    }

    public function isOverQuota(): bool
    {
        return $this->getConsumptionBytes() >= $this->getQuotaBytes();
    }

    /**
     * @return Collection<int, Credential>
     */
    public function getCredentials(): Collection
    {
        return $this->credentials;
    }

    public function addCredential(Credential $credential): void
    {
        if (!$this->credentials->contains($credential)) {
            $this->credentials->add($credential);
        }
    }
}
