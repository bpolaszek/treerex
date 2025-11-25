<?php

declare(strict_types=1);

namespace BenTools\TreeRex;

use function abs;
use function hash;

/**
 * @internal
 */
function generate_id(): string
{
    static $seed;
    $seed ??= 0;
    ++$seed;

    $min = 0;
    $max = 10_000_000_000;

    // Linear Congruential Generator (LCG) parameters
    $a = 1_664_525;
    $c = 1_013_904_223;
    $m = 2 ** 32;

    // Generate a pseudo-random number using LCG
    $seed = ($a * $seed + $c) % $m;

    // Ensure the result is within the desired range
    $randomNumber = $min + abs($seed) % ($max - $min + 1);

    return hash('xxh3', (string) $randomNumber);
}
