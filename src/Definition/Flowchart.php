<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Definition;

use ArrayObject;

final readonly class Flowchart
{
    /**
     * @var DecisionNode[]
     */
    private array $decisionNodes;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public array $context,
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
        foreach ($node->cases as $case) {
            if ($case instanceof DecisionNode) {
                self::register($case, $decisionNodes);
            }
        }

        return $decisionNodes;
    }
}
