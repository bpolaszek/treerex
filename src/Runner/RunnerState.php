<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Runner;

use ArrayAccess;
use ArrayObject;
use BenTools\TreeRex\Checker\CheckerInterface;
use BenTools\TreeRex\Definition\DecisionNode;
use BenTools\TreeRex\Definition\Flowchart;
use Traversable;

final class RunnerState
{
    public private(set) bool $lastResult;

    /**
     * @var array{0: string, 1: bool}[]
     */
    public private(set) array $history = [];

    /**
     * @param ArrayAccess<string, mixed>&Traversable<string, mixed> $context
     */
    public function __construct(
        public readonly DecisionNode $decisionNode,
        public readonly mixed $subject,
        public readonly Flowchart $flowchart,
        public readonly CheckerInterface $checker,
        public ArrayAccess&Traversable $context = new ArrayObject(),
    ) {
    }

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
        $state->context['_state'] = $state;

        return $state;
    }

    public function withLastResult(bool $result, DecisionNode $decisionNode): self
    {
        $clone = clone $this;
        $clone->lastResult = $result;
        $clone->history[] = [$decisionNode->id, $result];

        return $clone;
    }

    /**
     * @param (ArrayAccess<string, mixed>&Traversable<string, mixed>)|array<string, mixed> ...$contexts
     *
     * @internal
     */
    public function withAppendedContext((ArrayAccess&Traversable)|array ...$contexts): self
    {
        $clone = clone $this;
        foreach ($contexts as $context) {
            foreach ($context as $key => $value) {
                $clone->context[$key] = $value;
            }
        }

        $clone->context['_state'] = $clone;

        return $clone;
    }
}
