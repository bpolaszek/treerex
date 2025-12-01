<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Definition;

use ArrayAccess;
use ArrayObject;
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
}
