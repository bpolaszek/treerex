<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Action;

use BenTools\TreeRex\Exception\SkippedSteps;
use BenTools\TreeRex\Runner\RunnerContext;
use BenTools\TreeRex\Runner\RunnerState;

/**
 * @internal
 */
final readonly class GotoNode extends Action
{
    /**
     * @param RunnerContext<string, mixed> $context
     */
    public function __construct(
        public string $id,
        public RunnerContext $context = new RunnerContext(),
    ) {
    }

    /**
     * @throws SkippedSteps
     */
    public function __invoke(RunnerState $state): bool
    {
        throw new SkippedSteps($this->id, $state->withAppendedContext($this->context));
    }
}
