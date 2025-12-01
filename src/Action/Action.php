<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Action;

use BenTools\TreeRex\Runner\RunnerState;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
abstract readonly class Action
{
    abstract public function __invoke(RunnerState $state): bool|int|string;
}
