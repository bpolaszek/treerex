<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Factory;

use BenTools\TreeRex\Definition\Flowchart;
use BenTools\TreeRex\Exception\FlowchartBuildException;
use UnitEnum;

/**
 * @phpstan-type Context array<string, mixed>
 * @phpstan-type ReusableBlockDefinition array{
 *     id: string,
 *     checker?: string,
 *     label?: string,
 *     end?: EndDefinition|bool|int|string|UnitEnum,
 *     error?: ErrorDefinition|string,
 *     goto?: GotoDefinition|string,
 * }
 * @phpstan-type DecisionNodeDefinition array{
 *     checker: string,
 *     id?: string,
 *     label?: string,
 *     use?: string,
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
 *     nodes: DecisionNodeDefinition[],
 *     context?: Context,
 * }
 * @phpstan-type FlowchartOptions array{
 *     allowUnhandledCases?: bool,
 *     defaultChecker?: string,
 * }
 */
interface FlowchartFactoryInterface
{
    /**
     * @param FlowchartDefinition $flowchartDefinition
     * @param FlowchartOptions    $options
     *
     * @throws FlowchartBuildException
     */
    public function create(array $flowchartDefinition, array $options): Flowchart;
}
