<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Definition;

use BenTools\TreeRex\Action\Action;
use BenTools\TreeRex\Runner\RunnerContext;

/**
 * @internal
 */
final readonly class DecisionNode
{
    /**
     * @param RunnerContext<string, mixed> $context
     */
    public function __construct(
        public string $checkerServiceId,
        public string $id,
        public Cases $cases,
        public ?string $label = null,
        public mixed $criteria = null,
        public RunnerContext $context = new RunnerContext(),
    ) {
    }

    public function when(string|bool|int $result): DecisionNode|Action
    {
        return $this->cases->get($result);
    }
}
