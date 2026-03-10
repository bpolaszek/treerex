<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Action;

use BenTools\TreeRex\SideEffect\SideEffectTiming;

/**
 * Wraps an Action with a side-effect that executes before or after the action.
 * Resolution and execution of the side-effect is handled by the FlowchartRunner.
 *
 * @internal
 */
final readonly class WithSideEffect
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public Action $action,
        public string $sideEffectServiceId,
        public SideEffectTiming $timing = SideEffectTiming::BEFORE,
        public array $context = [],
    ) {
    }
}
