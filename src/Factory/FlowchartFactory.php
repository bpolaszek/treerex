<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Factory;

use BenTools\TreeRex\Action\Action;
use BenTools\TreeRex\Action\EndFlow;
use BenTools\TreeRex\Action\GotoNode;
use BenTools\TreeRex\Action\RaiseError;
use BenTools\TreeRex\Action\UnhandledStep;
use BenTools\TreeRex\Definition\Cases;
use BenTools\TreeRex\Definition\DecisionNode;
use BenTools\TreeRex\Definition\Flowchart;
use BenTools\TreeRex\Exception\FlowchartBuildException;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_diff_key;
use function array_filter;
use function array_find;
use function array_key_exists;
use function array_walk;
use function assert;
use function is_array;
use function sprintf;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;

/**
 * @phpstan-import-type FlowchartOptions from FlowchartFactoryInterface
 * @phpstan-import-type ReusableBlockDefinition from FlowchartFactoryInterface
 * @phpstan-import-type DecisionNodeDefinition from FlowchartFactoryInterface
 */
final readonly class FlowchartFactory implements FlowchartFactoryInterface
{
    private OptionsResolver $flowchartResolver;
    private OptionsResolver $flowchartOptionsResolver;
    private OptionsResolver $decisionNodeResolver;

    public function __construct()
    {
        $this->flowchartResolver = FlowchartDefinitionHelper::getFlowchartResolver();
        $this->flowchartOptionsResolver = FlowchartDefinitionHelper::getFlowchartOptionsResolver();
        $this->decisionNodeResolver = FlowchartDefinitionHelper::getDecisionNodeResolver();
    }

    public function create(array $flowchartDefinition, array $options = []): Flowchart
    {
        $flowchartDefinition = $this->flowchartResolver->resolve($flowchartDefinition);
        $flowchartOptions = $this->flowchartOptionsResolver->resolve([
            ...$flowchartDefinition['options'] ?? [],
            ...$options,
        ]);

        $blocks = $flowchartDefinition['blocks'] ?? [];
        // Ensure all blocks have an ID, or take the key as ID.
        array_walk($blocks, fn (array &$block, int|string $key) => $block['id'] ??= (string) $key);

        $entrypoint = $this->buildStep($flowchartDefinition['entrypoint'], $blocks, $flowchartOptions);
        assert($entrypoint instanceof DecisionNode);

        if (!$flowchartOptions['allowUnhandledCases']) {
            FlowchartDefinitionHelper::ensureNoUnhandledCases($entrypoint);
        }

        $context = FlowchartDefinitionHelper::toContext($flowchartDefinition['context'] ?? []);

        return new Flowchart($context, $entrypoint);
    }

    /**
     * @param bool|DecisionNodeDefinition|null $data
     * @param ReusableBlockDefinition[]        $blocks
     * @param FlowchartOptions                 $options
     */
    private function buildStep(bool|int|string|array|null $data, array $blocks, array $options): Action|DecisionNode
    {
        if (null === $data) {
            return new UnhandledStep();
        }

        if (!is_array($data)) {
            return new EndFlow($data);
        }

        if (isset($data['use'])) {
            $block = array_find($blocks, fn (array $block) => $block['id'] === $data['use'])
                ?? throw new FlowchartBuildException(sprintf('Block `%s` not found.', $data['use']));
            // @codeCoverageIgnoreStart
            $data = [...$block, ...$data];
            // @codeCoverageIgnoreEnd
            // That's actually covered, but Xdebug somehow doesn't detect it 😢
        }

        $exceptCases = array_filter($data, fn ($key) => !str_starts_with($key, 'when@'), ARRAY_FILTER_USE_KEY);
        $onlyCases = array_diff_key($data, $exceptCases);

        /** @var DecisionNodeDefinition $data */
        $exceptCases = $this->decisionNodeResolver->resolve($exceptCases);
        $data = [...$exceptCases, ...$onlyCases];

        return match (true) {
            array_key_exists('end', $data) => FlowchartDefinitionHelper::normalizeEnd($data['end']),
            array_key_exists('error', $data) => new RaiseError(...(array) $data['error']),
            array_key_exists('goto', $data) => new GotoNode(...(array) $data['goto']),
            default => $this->buildDecisionNode($data, $blocks, $options), // @phpstan-ignore argument.type
        };
    }

    /**
     * @param DecisionNodeDefinition    $data
     * @param ReusableBlockDefinition[] $blocks
     * @param FlowchartOptions          $options
     */
    private function buildDecisionNode(array $data, array $blocks, array $options): DecisionNode
    {
        $cases = $data['cases'] ?? [true, false];
        $id = $data['id'] ?? FlowchartDefinitionHelper::generateId();
        $checkerServiceId = $data['checker'] ?? $options['defaultChecker']
            ?? throw new FlowchartBuildException("No default checker defined at step `{$id}`.");

        $decisionNode = new DecisionNode(
            checkerServiceId: $checkerServiceId,
            id: $id,
            cases: new Cases($cases),
            label: $data['label'] ?? null,
            criteria: $data['criteria'] ?? null,
            context: FlowchartDefinitionHelper::toContext($data['context'] ?? []),
        );

        foreach ($cases as $case) {
            $key = sprintf('when@%s', Cases::stringify($case));
            $next = $data[$key] ?? null;
            if (null !== $next) {
                FlowchartDefinitionHelper::validateNode($next); // @phpstan-ignore argument.type
            }
            $decisionNode->cases->when($decisionNode->id, $case, $this->buildStep($next, $blocks, $options)); // @phpstan-ignore argument.type
        }

        return $decisionNode;
    }
}
