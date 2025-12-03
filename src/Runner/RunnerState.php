<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Runner;

use BenTools\TreeRex\Checker\CheckerInterface;
use BenTools\TreeRex\Definition\DecisionNode;
use BenTools\TreeRex\Definition\Flowchart;

final class RunnerState
{
    public private(set) bool|string|int $lastResult;

    /**
     * @var array{0: string, 1: bool|int|string}[]
     */
    public private(set) array $history = [];

    /**
     * @param RunnerContext<string, mixed> $context
     */
    public function __construct(
        public readonly DecisionNode $decisionNode,
        public readonly mixed $subject,
        public readonly Flowchart $flowchart,
        public readonly CheckerInterface $checker,
        public RunnerContext $context = new RunnerContext(),
    ) {
    }

    /**
     * @internal
     */
    public function with(
        ?DecisionNode $decisionNode = null,
        mixed $subject = null,
        ?Flowchart $flowchart = null,
        ?CheckerInterface $checker = null,
    ): self {
        $state = new self(
            $decisionNode ?? $this->decisionNode,
            $subject ?? $this->subject,
            $flowchart ?? $this->flowchart,
            $checker ?? $this->checker,
            $this->context,
        );
        $state->history = $this->history;
        $state->lastResult = $this->lastResult;
        $state->context->state = $state;

        return $state;
    }

    /**
     * @internal
     */
    public function withLastResult(bool|int|string $result, DecisionNode $decisionNode): self
    {
        $clone = clone $this;
        $clone->lastResult = $result;
        $clone->history[] = [$decisionNode->id, $result];

        return $clone;
    }

    /**
     * @param array<string, mixed> ...$contexts
     *
     * @internal
     */
    public function withAppendedContext(array ...$contexts): self
    {
        $clone = clone $this;
        foreach ($contexts as $context) {
            foreach ($context as $key => $value) {
                $clone->context[$key] = $value;
            }
        }

        $clone->context->state = $clone;

        return $clone;
    }
}
