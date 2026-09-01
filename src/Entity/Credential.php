<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'credentials')]
class Credential
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Organization::class, inversedBy: 'credentials')]
    #[ORM\JoinColumn(name: 'organization_id', nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(length: 255)]
    private string $username;

    #[ORM\Column]
    private bool $enabled;

    public function __construct(Organization $organization, string $username)
    {
        $this->id = Uuid::v7();
        $this->organization = $organization;
        $this->username = $username;
        $this->enabled = !$organization->isOverQuota();

        $organization->addCredential($this);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
