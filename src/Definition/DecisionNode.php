<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Definition;

use ArrayAccess;
use ArrayObject;
use BenTools\TreeRex\Action\Action;
use Traversable;

/**
 * @internal
 */
final readonly class DecisionNode
{
    /**
     * @param ArrayAccess<string, mixed>&Traversable<string, mixed> $context
     */
    public function __construct(
        public string $checkerServiceId,
        public string $id,
        public Cases $cases,
        public ?string $label = null,
        public mixed $criteria = null,
        public ArrayAccess&Traversable $context = new ArrayObject(),
    ) {
    }

    public function when(string|bool|int $result): DecisionNode|Action
    {
        return $this->cases->get($result);
    }
}
