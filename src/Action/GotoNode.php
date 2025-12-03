<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Action;

use BenTools\TreeRex\Exception\SkippedSteps;
use BenTools\TreeRex\Runner\RunnerState;

/**
 * @internal
 */
final readonly class GotoNode extends Action
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $id,
        public array $context = [],
    ) {
    }

    /**
     * @throws SkippedSteps
     */
    public function __invoke(RunnerState $state): never
    {
        throw new SkippedSteps($this->id, $state->withAppendedContext($this->context));
    }
}
