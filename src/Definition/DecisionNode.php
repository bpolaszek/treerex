<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Definition;

use BenTools\TreeRex\Action\Action;
use UnitEnum;

/**
 * @internal
 */
final readonly class DecisionNode
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $checkerServiceId,
        public string $id,
        public Cases $cases,
        public ?string $label = null,
        public mixed $criteria = null,
        public array $context = [],
    ) {
    }

    /**
     * @internal
     */
    public function whenResultIs(string|bool|int|UnitEnum $result): DecisionNode|Action
    {
        return $this->cases->get($result);
    }
}
