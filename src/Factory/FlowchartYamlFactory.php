<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Factory;

use BenTools\TreeRex\Definition\Flowchart;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * @phpstan-import-type FlowchartDefinition from FlowchartFactoryInterface
 */
final readonly class FlowchartYamlFactory
{
    public function __construct(
        private FlowchartFactoryInterface $factory = new FlowchartFactory(),
    ) {
    }

    public function parseYamlFile(string $filename): Flowchart
    {
        if (!is_file($filename)) {
            throw new InvalidArgumentException(sprintf('YAML file not found: %s', $filename));
        }

        /** @var FlowchartDefinition */
        $parsed = Yaml::parseFile($filename);

        return $this->factory->create($parsed);
    }
}
