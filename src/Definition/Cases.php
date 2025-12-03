<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Definition;

use BackedEnum;
use BenTools\TreeRex\Action\Action;
use BenTools\TreeRex\Action\UnhandledStep;
use BenTools\TreeRex\Exception\FlowchartBuildException;
use IteratorAggregate;
use RuntimeException;
use Traversable;
use UnitEnum;

use function array_column;
use function array_filter;
use function array_find;
use function BenTools\TreeRex\safe_array_diff;
use function BenTools\TreeRex\safe_array_unique;
use function sprintf;

/**
 * @internal
 *
 * @implements IteratorAggregate<string|bool|int|UnitEnum, DecisionNode|Action>
 */
final class Cases implements IteratorAggregate
{
    /**
     * @var array{0: string|bool|int|UnitEnum, 1: DecisionNode|Action}[]
     */
    public private(set) array $conditions = [];

    /**
     * @param list<bool|string|int|UnitEnum> $cases
     */
    public function __construct(private readonly array $cases)
    {
    }

    public function when(string $decisionNodeId, string|bool|int|UnitEnum $result, DecisionNode|Action $next): void
    {
        if (array_find($this->conditions, fn (array $condition) => $condition[0] === $result)) {
            throw new FlowchartBuildException(sprintf('`%s`: Case `%s` is already defined.', $decisionNodeId, self::stringify($result)));
        }
        $this->conditions[] = [$result, $next];
    }

    public function get(string|bool|int|UnitEnum $result): DecisionNode|Action
    {
        [, $next] = array_find($this->conditions, fn (array $condition) => $condition[0] === $result)
            ?? throw new RuntimeException(sprintf('No case found for result: `%s`.', self::stringify($result)));

        return $next;
    }

    /**
     * @return list<bool|string|int|UnitEnum>
     */
    public function getUnHandledCases(): array
    {
        $missingCases = safe_array_diff($this->cases, array_column($this->conditions, 0));
        $explicitelyUnhandledCases = array_column(
            array_filter(
                $this->conditions,
                fn (array $condition) => $condition[1] instanceof UnhandledStep,
            ),
            0,
        );

        return safe_array_unique([...$missingCases, ...$explicitelyUnhandledCases]);
    }

    public function getIterator(): Traversable
    {
        foreach ($this->conditions as [$condition, $next]) {
            yield $condition => $next;
        }
    }

    public static function stringify(bool|int|string|UnitEnum $case): string
    {
        return match (true) {
            true === $case => 'true',
            false === $case => 'false',
            $case instanceof BackedEnum => (string) $case->value,
            $case instanceof UnitEnum => $case->name,
            default => (string) $case,
        };
    }
}
