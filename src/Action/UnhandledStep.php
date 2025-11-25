<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Action;

use BenTools\TreeRex\Exception\UnhandledStepException;
use BenTools\TreeRex\Runner\RunnerState;

/**
 * @internal
 */
final readonly class UnhandledStep extends Action
{
    public function __invoke(RunnerState $state): never
    {
        throw new UnhandledStepException($state);
    }
}
