<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Action;

use BenTools\TreeRex\Exception\FlowchartRuntimeException;
use BenTools\TreeRex\Runner\RunnerContext;
use BenTools\TreeRex\Runner\RunnerState;
use RuntimeException;

use function assert;
use function is_a;

/**
 * @internal
 */
final readonly class RaiseError extends Action
{
    /**
     * @param RunnerContext<string, mixed> $context
     */
    public function __construct(
        public string $message = 'An error occurred while running the flowchart.',
        public string $exceptionClass = RuntimeException::class,
        public RunnerContext $context = new RunnerContext(),
    ) {
        assert(is_a($this->exceptionClass, RuntimeException::class, true));
    }

    public function __invoke(RunnerState $state): never
    {
        $exceptionClass = $this->exceptionClass;

        throw new $exceptionClass($this->message, previous: new FlowchartRuntimeException($state->withAppendedContext($this->context), $this->message));
    }
}
