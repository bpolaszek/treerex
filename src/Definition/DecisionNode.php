<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Definition;

use ArrayAccess;
use ArrayObject;
use BenTools\TreeRex\Action\Action;
use BenTools\TreeRex\Action\UnhandledStep;
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
        public ?string $label = null,
        public mixed $criteria = null,
        public Action|DecisionNode $whenYes = new UnhandledStep(),
        public Action|DecisionNode $whenNo = new UnhandledStep(),
        public ArrayAccess&Traversable $context = new ArrayObject(),
    ) {
    }
}
