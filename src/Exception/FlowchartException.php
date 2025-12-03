<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Exception;

use BenTools\TreeRex\Runner\RunnerState;
use RuntimeException;
use Throwable;

abstract class FlowchartException extends RuntimeException
{
    public function __construct(
        public readonly RunnerState $state,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
