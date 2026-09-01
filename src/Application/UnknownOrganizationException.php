<?php

declare(strict_types=1);

namespace App\Application;

final class UnknownOrganizationException extends \RuntimeException
{
    public function __construct(string $organizationId)
    {
        parent::__construct(\sprintf('Organization "%s" not found.', $organizationId));
    }
}
