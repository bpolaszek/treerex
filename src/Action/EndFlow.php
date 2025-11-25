<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Action;

use ArrayAccess;
use ArrayObject;
use BenTools\TreeRex\Runner\RunnerState;
use Traversable;

/**
 * @internal
 */
final readonly class EndFlow extends Action
{
    /**
     * @param ArrayAccess<string, mixed>&Traversable<string, mixed> $context
     */
    public function __construct(
        public ?bool $result,
        public ArrayAccess&Traversable $context = new ArrayObject(),
    ) {
    }

    public function __invoke(RunnerState $state): bool
    {
        return $state
            ->withAppendedContext($this->context)
            ->withLastResult($this->result ?? $state->lastResult, $state->decisionNode)
            ->lastResult;
    }
}
