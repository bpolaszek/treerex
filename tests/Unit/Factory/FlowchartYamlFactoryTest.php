<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Tests\Unit;

use BenTools\TreeRex\Definition\Flowchart;
use BenTools\TreeRex\Factory\FlowchartYamlFactory;
use InvalidArgumentException;

use function expect;
use function file_put_contents;
use function hash;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

it('creates a flowchart from a yaml file', function () {
    $content = <<<YAML
entrypoint:
    checker: some.checker.service
YAML;

    $filepath = sprintf('%s/%s.yaml', sys_get_temp_dir(), hash('xxh3', random_bytes(16)));
    file_put_contents($filepath, $content);

    try {
        $factory = new FlowchartYamlFactory();
        $flowchart = $factory->parseYamlFile($filepath);
    } finally {
        unlink($filepath);
    }

    expect($flowchart ?? null)->toBeInstanceOf(Flowchart::class)
        ->and($flowchart->entrypoint->checkerServiceId)->toBe('some.checker.service');
});

it('complains when file does not exist', function () {
    $filepath = sprintf('%s/%s.yaml', sys_get_temp_dir(), hash('xxh3', random_bytes(16)));
    expect(fn () => new FlowchartYamlFactory()->parseYamlFile($filepath))
        ->toThrow(InvalidArgumentException::class);
});
