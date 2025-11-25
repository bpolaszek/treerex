<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Tests\Unit\Checker;

use ArrayObject;
use BenTools\TreeRex\Checker\ExpressionLanguageChecker;
use InvalidArgumentException;
use RuntimeException;

it('checks expressions', function () {
    $checker = new ExpressionLanguageChecker();
    $subject = (object) ['foo' => 'bar'];
    $context = new ArrayObject();
    expect($checker->satisfies($subject, 'subject.foo === "bar"', $context))->toBeTrue()
        ->and($checker->satisfies($subject, 'subject.foo === "baz"', $context))->toBeFalse();
});

it('checks multiple expressions', function () {
    $checker = new ExpressionLanguageChecker();
    $subject = (object) ['foo' => 'bar', 'bar' => 'foo'];
    $context = new ArrayObject();
    expect($checker->satisfies($subject, ['subject.foo === "bar"', 'subject.bar === "foo"'], $context))->toBeTrue()
        ->and(
            $checker->satisfies($subject, ['subject.foo === "bar"', 'subject.bar === "fooz"'], $context)
        )->toBeFalse();
});

it('complains when expression does not return a boolean', function () {
    $checker = new ExpressionLanguageChecker();
    $subject = (object) ['foo' => 'bar'];
    $context = new ArrayObject();
    expect(fn () => $checker->satisfies($subject, 'subject.foo', $context))->toThrow(RuntimeException::class);
});

it('complains when expression is not a string', function () {
    $checker = new ExpressionLanguageChecker();
    $subject = (object) ['foo' => 'bar'];
    $context = new ArrayObject();
    expect(fn () => $checker->satisfies($subject, (object) ['subject.foo === "bar"'], $context))->toThrow(InvalidArgumentException::class);
});
