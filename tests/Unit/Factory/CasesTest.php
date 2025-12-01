<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Tests\Unit\Factory;

use BenTools\TreeRex\Action\UnhandledStep;
use BenTools\TreeRex\Definition\Cases;
use BenTools\TreeRex\Exception\FlowchartBuildException;
use RuntimeException;

it('denies duplicate cases', function () {
    $cases = new Cases([true]);
    $cases->when('foo', true, new UnhandledStep());

    expect(fn () => $cases->when('foo', true, new UnhandledStep()))
        ->toThrow(FlowchartBuildException::class, '`foo`: Case `true` is already defined.');
});

it('complains when it cannot find a case', function () {
    $cases = new Cases([true]);
    $cases->when('foo', true, new UnhandledStep());

    expect(fn () => $cases->get(false))
        ->toThrow(RuntimeException::class, 'No case found for result: `false`.');
});
