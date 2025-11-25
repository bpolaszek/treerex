<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Checker;

use ArrayAccess;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Traversable;

use function array_all;
use function get_debug_type;
use function is_bool;

final readonly class ExpressionLanguageChecker implements CheckerInterface
{
    public function __construct(
        private string $subjectVariable = 'subject',
        private ExpressionLanguage $expressionLanguage = new ExpressionLanguage(),
    ) {
    }

    public function satisfies(mixed $subject, mixed $criteria, ArrayAccess&Traversable $context): bool
    {
        $criteriaType = get_debug_type($criteria);

        return match ($criteriaType) {
            'string' => $this->check($criteria, $subject, $context), // @phpstan-ignore argument.type
            'array' => array_all($criteria, fn ($expression) => $this->check($expression, $subject, $context)), // @phpstan-ignore argument.type, argument.type
            default => throw new InvalidArgumentException('Invalid criteria type: '.$criteriaType.'. Must be a string or an array of strings.'),
        };
    }

    /**
     * @param ArrayAccess<string, mixed>&Traversable<string, mixed> $context
     */
    private function check(string $expression, mixed $subject, ArrayAccess&Traversable $context): bool
    {
        $result = $this->expressionLanguage->evaluate($expression, [
            $this->subjectVariable => $subject,
            'context' => (object) [...$context],
        ]);

        if (!is_bool($result)) {
            throw new RuntimeException('ExpressionLanguage did not return a boolean.');
        }

        return $result;
    }
}
