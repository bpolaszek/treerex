<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Factory;

use BenTools\TreeRex\Definition\Flowchart;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * @phpstan-import-type FlowchartDefinition from FlowchartFactoryInterface
 * @phpstan-import-type FlowchartOptions from FlowchartFactoryInterface
 */
final readonly class FlowchartYamlFactory
{
    public function __construct(
        private FlowchartFactoryInterface $factory = new FlowchartFactory(),
    ) {
    }

    /**
     * @param int-mask-of<Yaml::PARSE_*> $flags
     * @param FlowchartOptions           $options
     */
    public function parseYamlFile(string $filename, int $flags = Yaml::PARSE_CONSTANT, array $options = []): Flowchart
    {
        if (!is_file($filename)) {
            throw new InvalidArgumentException(sprintf('YAML file not found: %s', $filename));
        }

        /** @var FlowchartDefinition */
        $parsed = Yaml::parseFile($filename, $flags);

        return $this->factory->create($parsed, $options);
    }
}
