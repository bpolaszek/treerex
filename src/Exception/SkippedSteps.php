<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Exception;

use BenTools\TreeRex\Runner\RunnerState;
use Exception;

/**
 * @internal
 */
final class SkippedSteps extends Exception
{
    public function __construct(
        public readonly string $decisionNodeId,
        public readonly RunnerState $state,
    ) {
        parent::__construct();
    }
}
