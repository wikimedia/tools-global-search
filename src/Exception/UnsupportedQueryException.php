<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when a CirrusSearch query uses keywords that are not portable
 * across wikis and therefore cannot be used for global search.
 */
class UnsupportedQueryException extends \RuntimeException
{
    /** @var string[] */
    private array $violations;

    /** @param string[] $violations Human-readable descriptions of each violation. */
    public function __construct(array $violations)
    {
        $this->violations = $violations;
        parent::__construct('Query uses unsupported keywords: ' . implode('; ', $violations));
    }

    /** @return string[] */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
