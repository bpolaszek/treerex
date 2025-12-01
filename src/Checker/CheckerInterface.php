<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Checker;

use ArrayAccess;
use Traversable;

interface CheckerInterface
{
    /**
     * @param ArrayAccess<string, mixed>&Traversable<string, mixed> $context
     */
    public function satisfies(mixed $subject, mixed $criteria, ArrayAccess&Traversable $context): bool|int|string;
}
