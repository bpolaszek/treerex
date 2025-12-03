<?php

declare(strict_types=1);

namespace BenTools\TreeRex;

use function array_filter;
use function array_values;
use function in_array;

/**
 * @template T of array
 *
 * @param T $a
 * @param T $b
 *
 * @return T
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
function safe_array_diff(array $a, array $b): array
{
    // @phpstan-ignore return.type
    return array_filter($a, static fn ($value, $key) => !isset($b[$key]) || $b[$key] !== $value, ARRAY_FILTER_USE_BOTH);
}

/**
 * @template T of array
 *
 * @param T $array
 *
 * @return T
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
function safe_array_unique(array $array, bool $asValues = true): array
{
    $output = [];
    foreach ($array as $key => $value) {
        if (in_array($value, $output, true)) {
            continue;
        }
        $output[$key] = $value;
    }

    if ($asValues) {
        $output = array_values($output);
    }

    return $output; // @phpstan-ignore return.type
}
