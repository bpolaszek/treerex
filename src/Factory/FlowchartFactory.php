<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Factory;

use ArrayAccess;
use ArrayObject;
use BenTools\TreeRex\Action\Action;
use BenTools\TreeRex\Action\EndFlow;
use BenTools\TreeRex\Action\GotoNode;
use BenTools\TreeRex\Action\RaiseError;
use BenTools\TreeRex\Action\UnhandledStep;
use BenTools\TreeRex\Checker\CheckerInterface;
use BenTools\TreeRex\Definition\Cases;
use BenTools\TreeRex\Definition\DecisionNode;
use BenTools\TreeRex\Definition\Flowchart;
use BenTools\TreeRex\Exception\FlowchartBuildException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Traversable;

use function array_all;
use function array_diff_key;
use function array_filter;
use function array_find;
use function array_key_exists;
use function array_keys;
use function array_walk;
use function assert;
use function BenTools\TreeRex\generate_id;
use function count;
use function get_debug_type;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;

/**
 * @phpstan-import-type DecisionNodeDefinition from FlowchartFactoryInterface
 * @phpstan-import-type ReusableBlockDefinition from FlowchartFactoryInterface
 * @phpstan-import-type EndDefinition from FlowchartFactoryInterface
 * @phpstan-import-type ErrorDefinition from FlowchartFactoryInterface
 * @phpstan-import-type GotoDefinition from FlowchartFactoryInterface
 */
final readonly class FlowchartFactory implements FlowchartFactoryInterface
{
    private const array FLOWCHART_ROOT_KEYS = ['entrypoint', 'context', 'blocks'];
    private const array FLOWCHART_DECISION_NODE_KEYS = [
        'checker',
        'id',
        'label',
        'cases',
        'criteria',
        'context',
        'end',
        'goto',
        'error',
        'use',
    ];
    private const array FLOWCHART_ACTIONS = ['end', 'goto', 'error'];
    private OptionsResolver $flowchartResolver;
    private OptionsResolver $decisionNodeResolver;

    public function __construct()
    {
        $this->flowchartResolver = new OptionsResolver()
            ->setDefined(self::FLOWCHART_ROOT_KEYS)
            ->setRequired(['entrypoint'])
            ->setAllowedTypes('context', ['array', 'null']);

        $this->decisionNodeResolver = new OptionsResolver();
        $this->decisionNodeResolver->setDefined(self::FLOWCHART_DECISION_NODE_KEYS);
        $this->decisionNodeResolver->setAllowedTypes('checker', ['string', 'null', CheckerInterface::class]);
        $this->decisionNodeResolver->setAllowedTypes('id', ['string', 'null']);
        $this->decisionNodeResolver->setAllowedTypes('label', ['string', 'null']);
        $this->decisionNodeResolver->setAllowedTypes('use', ['string', 'null']);
        $this->decisionNodeResolver->setAllowedTypes('cases', ['(bool|int|string)[]', 'null']);
        $this->decisionNodeResolver->setAllowedTypes('end', ['bool', 'array']);
        $this->decisionNodeResolver->setAllowedTypes('error', ['string', 'array']);
        $this->decisionNodeResolver->setAllowedTypes('goto', ['string', 'array']);
        $this->decisionNodeResolver->setAllowedValues('end', self::validateEnd(...));
        $this->decisionNodeResolver->setAllowedValues('error', self::validateError(...));
        $this->decisionNodeResolver->setAllowedValues('goto', self::validateGoto(...));
    }

    public function create(array $flowchartDefinition, bool $allowUnhandledCases = true): Flowchart
    {
        $flowchartDefinition = $this->flowchartResolver->resolve($flowchartDefinition);

        $blocks = $flowchartDefinition['blocks'] ?? [];
        // Ensure all blocks have an ID, or take the key as ID.
        array_walk($blocks, fn (array &$block, int|string $key) => $block['id'] ??= (string) $key);

        $entrypoint = $this->buildStep($flowchartDefinition['entrypoint'], $blocks);
        assert($entrypoint instanceof DecisionNode);

        if (!$allowUnhandledCases) {
            $this->ensureNoUnhandledCases($entrypoint);
        }

        $context = self::toContext($flowchartDefinition['context'] ?? []);

        return new Flowchart($context, $entrypoint);
    }

    /**
     * @param bool|DecisionNodeDefinition|null $data
     * @param ReusableBlockDefinition[]        $blocks
     */
    private function buildStep(bool|int|string|array|null $data, array $blocks): Action|DecisionNode
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
            array_key_exists('end', $data) => self::normalizeEnd($data['end']),
            array_key_exists('error', $data) => new RaiseError(...(array) $data['error']),
            array_key_exists('goto', $data) => new GotoNode(...(array) $data['goto']),
            default => $this->buildDecisionNode($data, $blocks), // @phpstan-ignore argument.type
        };
    }

    /**
     * @param DecisionNodeDefinition    $data
     * @param ReusableBlockDefinition[] $blocks
     */
    private function buildDecisionNode(array $data, array $blocks): DecisionNode
    {
        $cases = $data['cases'] ?? [true, false];

        $decisionNode = new DecisionNode(
            checkerServiceId: $data['checker'],
            id: $data['id'] ?? generate_id(),
            cases: new Cases($cases),
            label: $data['label'] ?? null,
            criteria: $data['criteria'] ?? null,
            context: self::toContext($data['context'] ?? []),
        );

        foreach ($cases as $case) {
            $key = sprintf('when@%s', Cases::stringify($case));
            $next = $data[$key] ?? null;
            if (null !== $next) {
                self::validateNode($next); // @phpstan-ignore argument.type
            }
            $decisionNode->cases->when($decisionNode->id, $case, $this->buildStep($next, $blocks)); // @phpstan-ignore argument.type
        }

        return $decisionNode;
    }

    private function ensureNoUnhandledCases(DecisionNode $decisionNode): void
    {
        $unhandledCases = $decisionNode->cases->getUnHandledCases();
        if ($unhandledCases) {
            throw new FlowchartBuildException(sprintf('Cases `%s` are not handled at step `%s`.', implode(', ', $unhandledCases), $decisionNode->id));
        }
        foreach ($decisionNode->cases as $step) {
            if ($step instanceof DecisionNode) {
                $this->ensureNoUnhandledCases($step);
            }
        }
    }

    /**
     * @param array<string,mixed> $values
     *
     * @return ArrayAccess<string, mixed>&Traversable<string, mixed>
     */
    private static function toContext(array $values): ArrayAccess&Traversable
    {
        return new ArrayObject($values);
    }

    /**
     * @param bool|EndDefinition $data
     */
    private static function normalizeEnd(bool|array $data): EndFlow
    {
        return match (is_array($data)) {
            true => new EndFlow($data['result'] ?? null, self::toContext($data['context'] ?? [])),
            false => new EndFlow($data),
        };
    }

    /**
     * @param bool|int|string|DecisionNodeDefinition $node
     */
    private static function validateNode(bool|int|string|array $node): void
    {
        if (!is_array($node)) {
            return;
        }

        $actions = array_filter(
            array_keys($node),
            fn (string $prop) => in_array($prop, self::FLOWCHART_ACTIONS, true),
        );

        if (count($actions) > 1) {
            throw new InvalidOptionsException('Cannot have several actions wihin 1 node.');
        }
    }

    /**
     * @param bool|int|string|EndDefinition $end
     */
    private static function validateEnd(bool|int|string|array $end): bool
    {
        if (!is_array($end)) {
            return true;
        }

        $keys = array_keys($end);
        if (!array_all($keys, fn (string $key) => in_array($key, ['result', 'context'], true))) {
            throw new InvalidOptionsException('The `end` node must contain either `result` or `context`.');
        }

        if (!in_array(get_debug_type($end['result'] ?? null), ['bool', 'null'], true)) {
            throw new InvalidOptionsException('`end[result]` should be either `bool` or `null`.');
        }

        if (!in_array(get_debug_type($end['context'] ?? null), ['array', 'null'], true)) {
            throw new InvalidOptionsException('`end[context]` should be either `array` or `null`.');
        }

        return true;
    }

    /**
     * @param string|ErrorDefinition|null $error
     */
    private static function validateError(string|array|null $error): bool
    {
        if (is_string($error) || null === $error) {
            return true;
        }

        $keys = array_keys($error);
        if (!array_all($keys, fn (string $key) => in_array($key, ['message', 'exceptionClass', 'context'], true))) {
            throw new InvalidOptionsException('The `error` node must contain either `message`, `exceptionClass` or `context`.');
        }

        if (!in_array(get_debug_type($error['message'] ?? null), ['string', 'null'], true)) {
            throw new InvalidOptionsException('`error[message]` should be either `string` or `null`.');
        }

        if (!in_array(get_debug_type($error['exceptionClass'] ?? null), ['string', 'null'], true)) {
            throw new InvalidOptionsException('`error[exceptionClass]` should be either `string` or `null`.');
        }

        if (!in_array(get_debug_type($error['context'] ?? null), ['array', 'null'], true)) {
            throw new InvalidOptionsException('`error[context]` should be either `array` or `null`.');
        }

        return true;
    }

    /**
     * @param string|GotoDefinition|null $goto
     */
    private static function validateGoto(string|array|null $goto): bool
    {
        if (is_string($goto) || null === $goto) {
            return true;
        }

        $keys = array_keys($goto);
        if (!array_all($keys, fn (string $key) => in_array($key, ['id', 'context'], true))) {
            throw new InvalidOptionsException('The `goto` node must contain either `id` or `context`.');
        }

        if ('string' !== get_debug_type($goto['id'] ?? null)) {
            throw new InvalidOptionsException('`goto[id]` should be a `string`.');
        }

        if (!in_array(get_debug_type($goto['context'] ?? null), ['array', 'null'], true)) {
            throw new InvalidOptionsException('`goto[context]` should be either `array` or `null`.');
        }

        return true;
    }
}
