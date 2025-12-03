<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Tests\Unit\Checker;

use BenTools\TreeRex\Checker\ExpressionLanguageChecker;
use BenTools\TreeRex\Runner\RunnerContext;
use InvalidArgumentException;

it('checks expressions', function () {
    $checker = new ExpressionLanguageChecker();
    $subject = (object) ['foo' => 'bar'];
    $context = new RunnerContext();
    expect($checker->satisfies($subject, 'subject.foo === "bar"', $context))->toBeTrue()
        ->and($checker->satisfies($subject, 'subject.foo === "baz"', $context))->toBeFalse();
});

it('checks multiple expressions', function () {
    $checker = new ExpressionLanguageChecker();
    $subject = (object) ['foo' => 'bar', 'bar' => 'foo'];
    $context = new RunnerContext();
    expect($checker->satisfies($subject, ['subject.foo === "bar"', 'subject.bar === "foo"'], $context))->toBeTrue()
        ->and(
            $checker->satisfies($subject, ['subject.foo === "bar"', 'subject.bar === "fooz"'], $context)
        )->toBeFalse();
});

it('complains when expression is not a string', function () {
    $checker = new ExpressionLanguageChecker();
    $subject = (object) ['foo' => 'bar'];
    $context = new RunnerContext();
    expect(fn () => $checker->satisfies($subject, (object) ['subject.foo === "bar"'], $context))->toThrow(InvalidArgumentException::class);
});
