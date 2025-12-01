<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Factory;

use BenTools\TreeRex\Definition\Flowchart;
use BenTools\TreeRex\Exception\FlowchartBuildException;

/**
 * @phpstan-type Context array<string, mixed>
 * @phpstan-type DecisionNodeDefinition array{
 *     checker: string,
 *     id?: string,
 *     label?: string,
 *     end?: EndDefinition|bool,
 *     error?: ErrorDefinition|string,
 *     goto?: GotoDefinition|string,
 * }
 * @phpstan-type EndDefinition array{
 *     result?: bool,
 *     context?: array<string, mixed>,
 * }
 * @phpstan-type ErrorDefinition array{
 *     message?: string,
 *     exceptionClass?: string,
 *     context?: array<string, mixed>,
 * }
 * @phpstan-type GotoDefinition array{
 *     id: string,
 *     context?: array<string, mixed>,
 * }
 * @phpstan-type FlowchartDefinition array{
 *     entrypoint: DecisionNodeDefinition,
 *     context?: Context,
 * }
 */
interface FlowchartFactoryInterface
{
    /**
     * @param FlowchartDefinition $flowchartDefinition
     *
     * @throws FlowchartBuildException
     */
    public function create(array $flowchartDefinition, bool $allowUnhandledCases = true): Flowchart;
}
