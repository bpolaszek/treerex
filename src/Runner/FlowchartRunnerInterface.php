<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Runner;

use BenTools\TreeRex\Definition\Flowchart;

interface FlowchartRunnerInterface
{
    /**
     * @param RunnerContext<string, mixed>|array<string, mixed> $context
     */
    public function satisfies(
        mixed $subject,
        Flowchart|string $flowchart,
        RunnerContext|array $context = new RunnerContext(),
    ): bool|int|string;
}
