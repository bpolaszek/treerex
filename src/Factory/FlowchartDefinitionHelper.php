<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Factory;

use BenTools\TreeRex\Action\EndFlow;
use BenTools\TreeRex\Definition\Cases;
use BenTools\TreeRex\Definition\DecisionNode;
use BenTools\TreeRex\Exception\FlowchartBuildException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use UnitEnum;

use function abs;
use function array_filter;
use function array_keys;
use function array_map;
use function count;
use function hash;
use function implode;
use function in_array;
use function is_array;
use function sprintf;

/**
 * @internal
 *
 * @phpstan-import-type DecisionNodeDefinition from FlowchartFactoryInterface
 * @phpstan-import-type EndDefinition from FlowchartFactoryInterface
 * @phpstan-import-type ErrorDefinition from FlowchartFactoryInterface
 * @phpstan-import-type GotoDefinition from FlowchartFactoryInterface
 */
final readonly class FlowchartDefinitionHelper
{
    private const array FLOWCHART_ROOT_KEYS = ['entrypoint', 'context', 'blocks', 'options'];
    private const array FLOWCHART_OPTIONS_KEYS = ['allowUnhandledCases', 'defaultChecker'];
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
    private const array FLOWCHART_ACTION_KEYS = ['end', 'goto', 'error'];

    public static function getFlowchartResolver(): OptionsResolver
    {
        static $resolver;

        return $resolver ??= new OptionsResolver()
            ->setDefined(self::FLOWCHART_ROOT_KEYS)
            ->setRequired(['entrypoint'])
            ->setAllowedTypes('context', ['array', 'null'])
            ->setAllowedTypes('blocks', ['array', 'null'])
            ->setAllowedTypes('options', ['array', 'null']);
    }

    public static function getFlowchartOptionsResolver(): OptionsResolver
    {
        static $resolver;

        return $resolver ??= new OptionsResolver()
            ->setDefined(self::FLOWCHART_OPTIONS_KEYS)
            ->setAllowedTypes('allowUnhandledCases', ['bool'])
            ->setAllowedTypes('defaultChecker', ['string'])
            ->setDefaults(['allowUnhandledCases' => true]);
    }

    public static function getDecisionNodeResolver(): OptionsResolver
    {
        static $resolver;

        return $resolver ??= new OptionsResolver()
            ->setDefined(self::FLOWCHART_DECISION_NODE_KEYS)
            ->setAllowedTypes('checker', ['string', 'null'])
            ->setAllowedTypes('id', ['string', 'null'])
            ->setAllowedTypes('label', ['string', 'null'])
            ->setAllowedTypes('use', ['string', 'null'])
            ->setAllowedTypes('cases', ['(bool|int|string|UnitEnum)[]', 'null'])
            ->setAllowedTypes('end', ['bool', 'array'])
            ->setAllowedTypes('error', ['string', 'array'])
            ->setAllowedTypes('goto', ['string', 'array'])
            ->setAllowedValues('end', self::validateEnd(...))
            ->setAllowedValues('error', self::validateError(...))
            ->setAllowedValues('goto', self::validateGoto(...));
    }

    /**
     * @param bool|int|string|UnitEnum|EndDefinition $data
     */
    public static function normalizeEnd(bool|int|string|UnitEnum|array $data): EndFlow
    {
        return match (is_array($data)) {
            true => new EndFlow($data['result'] ?? null, $data['context'] ?? []),
            false => new EndFlow($data),
        };
    }

    /**
     * @param bool|int|string|UnitEnum|DecisionNodeDefinition $node
     */
    public static function validateNode(bool|int|string|UnitEnum|array $node): void
    {
        if (!is_array($node)) {
            return;
        }

        $actions = array_filter(
            array_keys($node),
            fn (string $prop) => in_array($prop, self::FLOWCHART_ACTION_KEYS, true),
        );

        if (count($actions) > 1) {
            throw new InvalidOptionsException('Cannot have several actions wihin 1 node.');
        }
    }

    /**
     * @param bool|int|string|UnitEnum|EndDefinition $end
     */
    private static function validateEnd(bool|int|string|UnitEnum|array $end): bool
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
        if (!is_array($error)) {
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
        if (!is_array($goto)) {
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

    public static function ensureNoUnhandledCases(DecisionNode $decisionNode): void
    {
        $unhandledCases = $decisionNode->cases->getUnHandledCases();
        if ($unhandledCases) {
            $unhandledCases = array_map(Cases::stringify(...), $unhandledCases);
            throw new FlowchartBuildException(sprintf('Cases `%s` are not handled at step `%s`.', implode(', ', array_map(Cases::stringify(...), $unhandledCases)), $decisionNode->id));
        }
        foreach ($decisionNode->cases as $step) {
            if ($step instanceof DecisionNode) {
                self::ensureNoUnhandledCases($step);
            }
        }
    }

    public static function generateId(): string
    {
        static $seed;
        $seed ??= 0;
        ++$seed;

        $min = 0;
        $max = 10_000_000_000;

        // Linear Congruential Generator (LCG) parameters
        $a = 1_664_525;
        $c = 1_013_904_223;
        $m = 2 ** 32;

        // Generate a pseudo-random number using LCG
        $seed = ($a * $seed + $c) % $m;

        // Ensure the result is within the desired range
        $randomNumber = $min + abs($seed) % ($max - $min + 1);

        return hash('xxh3', (string) $randomNumber);
    }
}
