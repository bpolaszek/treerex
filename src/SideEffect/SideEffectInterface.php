<?php

declare(strict_types=1);

namespace BenTools\TreeRex\SideEffect;

use BenTools\TreeRex\Runner\RunnerState;

interface SideEffectInterface
{
    public function execute(RunnerState $state): void;
}
