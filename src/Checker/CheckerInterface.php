<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Checker;

use BenTools\TreeRex\Runner\RunnerContext;

interface CheckerInterface
{
    /**
     * @param RunnerContext<string, mixed> $context
     */
    public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool|int|string;
}
