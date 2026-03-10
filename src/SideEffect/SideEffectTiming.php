<?php

declare(strict_types=1);

namespace BenTools\TreeRex\SideEffect;

enum SideEffectTiming: string
{
    case BEFORE = 'before';
    case AFTER = 'after';
}
