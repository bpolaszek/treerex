<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Exception;

use BenTools\TreeRex\Runner\RunnerState;
use Throwable;

final class SideEffectException extends FlowchartException
{
    public function __construct(RunnerState $state, string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($state, $message, $previous);
    }
}
