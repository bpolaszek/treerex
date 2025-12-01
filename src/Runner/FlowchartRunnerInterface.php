<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Runner;

use ArrayAccess;
use ArrayObject;
use BenTools\TreeRex\Definition\Flowchart;
use Traversable;

interface FlowchartRunnerInterface
{
    /**
     * @param (ArrayAccess<string, mixed>&Traversable<string, mixed>)|array<string, mixed> $context
     */
    public function satisfies(
        mixed $subject,
        Flowchart|string $flowchart,
        (ArrayAccess&Traversable)|array $context = new ArrayObject(),
    ): bool|int|string;
}
