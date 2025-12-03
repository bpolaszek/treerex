<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Checker;

use BenTools\TreeRex\Runner\RunnerContext;
use InvalidArgumentException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UnitEnum;

use function array_all;
use function get_debug_type;

final readonly class ExpressionLanguageChecker implements CheckerInterface
{
    public function __construct(
        private string $subjectVariable = 'subject',
        private ExpressionLanguage $expressionLanguage = new ExpressionLanguage(),
    ) {
    }

    public function satisfies(
        mixed $subject,
        mixed $criteria,
        RunnerContext $context,
    ): string|int|bool|UnitEnum {
        $criteriaType = get_debug_type($criteria);

        return match ($criteriaType) {
            'string' => $this->check($criteria, $subject, $context), // @phpstan-ignore argument.type
            'array' => array_all($criteria, fn ($expression) => $this->check($expression, $subject, $context)), // @phpstan-ignore argument.type, argument.type, argument.type
            default => throw new InvalidArgumentException('Invalid criteria type: '.$criteriaType.'. Must be a string or an array of strings.'),
        };
    }

    /**
     * @param RunnerContext<string, mixed> $context
     */
    private function check(
        string $expression,
        mixed $subject,
        RunnerContext $context,
    ): string|int|bool|UnitEnum {
        return $this->expressionLanguage->evaluate($expression, [ // @phpstan-ignore return.type
            $this->subjectVariable => $subject,
            'context' => (object) [...$context],
        ]);
    }
}
