<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Exception;

use BenTools\TreeRex\Runner\RunnerState;
use Throwable;

final class UnhandledStepException extends FlowchartException
{
    public function __construct(RunnerState $state, string $message = 'Unhandled step.', ?Throwable $previous = null)
    {
        parent::__construct($state, $message, $previous);
    }
}
