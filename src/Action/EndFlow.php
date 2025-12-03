<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Action;

use BenTools\TreeRex\Runner\RunnerState;
use UnitEnum;

/**
 * @internal
 */
final readonly class EndFlow extends Action
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public bool|int|string|UnitEnum|null $result,
        public array $context = [],
    ) {
    }

    public function __invoke(RunnerState $state): bool|int|string|UnitEnum
    {
        return $state
            ->withAppendedContext($this->context)
            ->withLastResult($this->result ?? $state->lastResult, $state->decisionNode)
            ->lastResult;
    }
}
