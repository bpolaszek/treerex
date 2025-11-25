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
use BenTools\TreeRex\Definition\DecisionNode;
use BenTools\TreeRex\Definition\Flowchart;
use BenTools\TreeRex\Exception\FlowchartBuildException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Traversable;

use function array_all;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function BenTools\TreeRex\generate_id;
use function count;
use function get_debug_type;
use function in_array;
use function is_bool;
use function is_string;

/**
 * @phpstan-import-type DecisionNodeDefinition from FlowchartFactoryInterface
 * @phpstan-import-type EndDefinition from FlowchartFactoryInterface
 * @phpstan-import-type ErrorDefinition from FlowchartFactoryInterface
 * @phpstan-import-type GotoDefinition from FlowchartFactoryInterface
 */
final class FlowchartFactory implements FlowchartFactoryInterface
{
    private const array FLOWCHART_ROOT_KEYS = ['entrypoint', 'context'];
    private const array FLOWCHART_DECISION_NODE_KEYS = [
        'checker',
        'id',
        'label',
        'criteria',
        'when@no',
        'when@yes',
        'context',
        'end',
        'goto',
        'error',
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
        $this->decisionNodeResolver->setAllowedTypes('when@yes', ['array', 'bool']);
        $this->decisionNodeResolver->setAllowedTypes('when@no', ['array', 'bool']);
        $this->decisionNodeResolver->setAllowedTypes('end', ['bool', 'array']);
        $this->decisionNodeResolver->setAllowedTypes('error', ['string', 'array']);
        $this->decisionNodeResolver->setAllowedTypes('goto', ['string', 'array']);
        $this->decisionNodeResolver->setAllowedValues('when@yes', self::validateNode(...));
        $this->decisionNodeResolver->setAllowedValues('when@no', self::validateNode(...));
        $this->decisionNodeResolver->setAllowedValues('end', self::validateEnd(...));
        $this->decisionNodeResolver->setAllowedValues('error', self::validateError(...));
        $this->decisionNodeResolver->setAllowedValues('goto', self::validateGoto(...));
    }

    public function create(array $flowchartDefinition, bool $allowUnhandledSteps = true): Flowchart
    {
        $flowchartDefinition = $this->flowchartResolver->resolve($flowchartDefinition);

        $context = $this->toContext($flowchartDefinition['context'] ?? []);
        $entrypoint = $this->buildStep($flowchartDefinition['entrypoint']);
        assert($entrypoint instanceof DecisionNode);

        if (!$allowUnhandledSteps) {
            $this->checkDecisionNode($entrypoint);
        }

        return new Flowchart($context, $entrypoint);
    }

    /**
     * @param DecisionNodeDefinition $data
     */
    private function buildDecisionNode(array $data): DecisionNode
    {
        return new DecisionNode(
            checkerServiceId: $data['checker'],
            id: $data['id'] ?? generate_id(),
            label: $data['label'] ?? null,
            criteria: $data['criteria'] ?? null,
            whenYes: $this->buildStep($data['when@yes'] ?? null),
            whenNo: $this->buildStep($data['when@no'] ?? null),
            context: $this->toContext($data['context'] ?? []),
        );
    }

    private function checkDecisionNode(DecisionNode $decisionNode): void
    {
        match (true) {
            $decisionNode->whenYes instanceof UnhandledStep => throw new FlowchartBuildException("Step `when@yes` is not defined at step `{$decisionNode->id}`."),
            $decisionNode->whenNo instanceof UnhandledStep => throw new FlowchartBuildException("Step `when@no` is not defined at step `{$decisionNode->id}`."),
            $decisionNode->whenYes instanceof DecisionNode => $this->checkDecisionNode($decisionNode->whenYes),
            $decisionNode->whenNo instanceof DecisionNode => $this->checkDecisionNode($decisionNode->whenNo),
            default => null,
        };
    }

    /**
     * @param bool|DecisionNodeDefinition|null $data
     */
    private function buildStep(bool|array|null $data): Action|DecisionNode
    {
        if (null === $data) {
            return new UnhandledStep();
        }

        if (is_bool($data)) {
            return new EndFlow($data);
        }

        /** @var DecisionNodeDefinition $data */
        $data = $this->decisionNodeResolver->resolve($data);

        return match (true) {
            array_key_exists('end', $data) => $this->normalizeEnd($data['end']),
            array_key_exists('error', $data) => new RaiseError(...(array) $data['error']),
            array_key_exists('goto', $data) => new GotoNode(...(array) $data['goto']),
            default => $this->buildDecisionNode($data),
        };
    }

    /**
     * @param array<string,mixed> $values
     *
     * @return ArrayAccess<string, mixed>&Traversable<string, mixed>
     */
    private function toContext(array $values): ArrayAccess&Traversable
    {
        return new ArrayObject($values);
    }

    /**
     * @param bool|EndDefinition $data
     */
    private function normalizeEnd(bool|array $data): EndFlow
    {
        return match (is_bool($data)) {
            true => new EndFlow($data),
            false => new EndFlow($data['result'] ?? null, $this->toContext($data['context'] ?? [])),
        };
    }

    /**
     * @param DecisionNodeDefinition $node
     */
    private static function validateNode(array|bool $node): bool
    {
        if (is_bool($node)) {
            return true;
        }

        $actions = array_filter(
            array_keys($node),
            fn (string $prop) => in_array($prop, self::FLOWCHART_ACTIONS, true),
        );

        if (count($actions) > 1) {
            throw new InvalidOptionsException('Cannot have several actions wihin 1 node.');
        }

        return true;
    }

    /**
     * @param bool|EndDefinition $end
     */
    private static function validateEnd(bool|array $end): bool
    {
        if (is_bool($end)) {
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
