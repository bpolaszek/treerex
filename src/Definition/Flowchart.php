<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Definition;

use ArrayAccess;
use ArrayObject;
use Traversable;

final readonly class Flowchart
{
    /**
     * @var DecisionNode[]
     */
    private array $decisionNodes;

    /**
     * @param ArrayAccess<string, mixed>&Traversable<string, mixed> $context
     */
    public function __construct(
        public ArrayAccess&Traversable $context,
        public DecisionNode $entrypoint,
    ) {
        $this->decisionNodes = [...self::register($entrypoint)];
    }

    public function findDecisionNodeById(string $id): ?DecisionNode
    {
        return array_find($this->decisionNodes, fn (DecisionNode $node) => $node->id === $id);
    }

    /**
     * @param ArrayObject<int, DecisionNode> $decisionNodes
     *
     * @return ArrayObject<int, DecisionNode>
     */
    private static function register(DecisionNode $node, ArrayObject $decisionNodes = new ArrayObject()): ArrayObject
    {
        $decisionNodes[] = $node;
        if ($node->whenYes instanceof DecisionNode) {
            self::register($node->whenYes, $decisionNodes);
        }
        if ($node->whenNo instanceof DecisionNode) {
            self::register($node->whenNo, $decisionNodes);
        }

        return $decisionNodes;
    }
}
