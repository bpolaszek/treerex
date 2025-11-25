<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Action;

use ArrayAccess;
use ArrayObject;
use BenTools\TreeRex\Exception\SkippedSteps;
use BenTools\TreeRex\Runner\RunnerState;
use Traversable;

/**
 * @internal
 */
final readonly class GotoNode extends Action
{
    /**
     * @param ArrayAccess<string, mixed>&Traversable<string, mixed> $context
     */
    public function __construct(
        public string $id,
        public ArrayAccess&Traversable $context = new ArrayObject(),
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
