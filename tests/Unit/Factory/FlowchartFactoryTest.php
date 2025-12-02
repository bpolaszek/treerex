<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Tests\Unit;

use BenTools\TreeRex\Action\EndFlow;
use BenTools\TreeRex\Action\GotoNode;
use BenTools\TreeRex\Action\RaiseError;
use BenTools\TreeRex\Action\UnhandledStep;
use BenTools\TreeRex\Definition\DecisionNode;
use BenTools\TreeRex\Exception\FlowchartBuildException;
use BenTools\TreeRex\Factory\FlowchartFactory;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Yaml\Yaml;

use function BenTools\CartesianProduct\combinations;
use function describe;
use function expect;
use function it;
use function json_encode;

describe('Flowchart Factory', function () {
    $factory = new FlowchartFactory();

    it('creates a flowchart from an array', function (array|bool $end, array|string $goto, array|string $error) use ($factory) {
        $flowchartDefinition = [
            'context' => ['foo' => 'bar'],
            'entrypoint' => [
                'checker' => 'some.checker.service',
                'id' => 'root',
                'label' => 'Root node',
                'when@false' => ['end' => $end],
                'when@true' => [
                    'checker' => 'some.other.checker.service',
                    'when@true' => ['goto' => $goto],
                    'when@false' => [
                        'checker' => 'yet.another.checker.service',
                        'when@false' => ['error' => $error],
                    ],
                ],
            ],
        ];
        $flowchart = $factory->create($flowchartDefinition);
        expect([...$flowchart->context])->toBe(['foo' => 'bar'])
            ->and($flowchart->entrypoint->checkerServiceId)->toBe('some.checker.service')
            ->and($flowchart->entrypoint->id)->toBe('root')
            ->and($flowchart->entrypoint->label)->toBe('Root node')
            ->and($flowchart->entrypoint->cases->get(false))->toBeInstanceOf(EndFlow::class)
            ->and($flowchart->entrypoint->cases->get(false)->result)->toBeFalse()
            ->and($flowchart->entrypoint->cases->get(true))->toBeInstanceOf(DecisionNode::class)
            ->and($flowchart->entrypoint->cases->get(true)->checkerServiceId)->toBe('some.other.checker.service')
            ->and($flowchart->entrypoint->cases->get(true)->cases->get(true))->toBeInstanceOf(GotoNode::class)
            ->and($flowchart->entrypoint->cases->get(true)->cases->get(true)->id)->toBe('root')
            ->and($flowchart->entrypoint->cases->get(true)->cases->get(false))->toBeInstanceOf(DecisionNode::class)
            ->and($flowchart->entrypoint->cases->get(true)->cases->get(false)->checkerServiceId)->toBe('yet.another.checker.service')
            ->and($flowchart->entrypoint->cases->get(true)->cases->get(false)->cases->get(false))->toBeInstanceOf(RaiseError::class)
            ->and($flowchart->entrypoint->cases->get(true)->cases->get(false)->cases->get(false)->message)->toBe('Ooops')
            ->and($flowchart->entrypoint->cases->get(true)->cases->get(false)->cases->get(true))->toBeInstanceOf(UnhandledStep::class);
    })->with(function () {
        $dataset = [
            'end' => [
                false,
                ['result' => false],
            ],
            'goto' => [
                'root',
                ['id' => 'root'],
            ],
            'error' => [
                'Ooops',
                ['message' => 'Ooops'],
            ],
        ];

        foreach (combinations($dataset) as $combination) {
            yield json_encode($combination) => $combination;
        }
    })->with(function () {
        $dataset = [
            'end' => [
                false,
                ['result' => false],
            ],
            'goto' => [
                'root',
                ['id' => 'root'],
            ],
            'error' => [
                'Ooops',
                ['message' => 'Ooops'],
            ],
        ];

        foreach (combinations($dataset) as $combination) {
            yield json_encode($combination) => $combination;
        }
    });

    it('spots unhandled steps', function () use ($factory) {
        $flowchartDefinition = [
            'context' => ['foo' => 'bar'],
            'entrypoint' => [
                'checker' => 'some.checker.service',
                'id' => 'root',
                'label' => 'Root node',
                'when@false' => ['end' => false],
                'when@true' => [
                    'checker' => 'some.other.checker.service',
                    'when@true' => ['goto' => 'root'],
                    'when@false' => [
                        'checker' => 'yet.another.checker.service',
                        'when@false' => ['error' => 'Ooops'],
                    ],
                ],
            ],
        ];
        expect(fn () => $factory->create($flowchartDefinition, ['allowUnhandledCases' => false]))
            ->toThrow(FlowchartBuildException::class);
    });
});

describe('Flowchart Factory Validation', function () {
    $factory = new FlowchartFactory();

    it('complains when a node contains several actions', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'checker.default',
                'when@true' => ['end' => true, 'goto' => 'root'],
                'when@false' => ['end' => false],
            ],
        ];

        expect(fn () => $factory->create($definition))->toThrow(InvalidOptionsException::class);
    });

    it('denies invalid `end` keys', function () use ($factory) {
        $decisionNode = [
            'checker' => 'checker.default',
        ];

        $invalidCases = [
            ['when@true' => ['end' => ['foo' => 'bar']]], // <-- Invalid keys in end definition
            ['when@true' => ['end' => ['result' => 'nope']]], // <-- Invalid type for end[result]
            ['when@true' => ['end' => ['context' => 'nope']]], // <-- Invalid type for end[context]
        ];

        foreach ($invalidCases as $invalidCase) {
            $definition = [
                'entrypoint' => [...$decisionNode, ...$invalidCase],
            ];
            expect(fn () => $factory->create($definition))->toThrow(InvalidOptionsException::class);
        }
    });

    it('denies invalid `error` structure and types', function () use ($factory) {
        $decisionNode = [
            'checker' => 'checker.default',
            'when@false' => ['end' => false],
        ];

        $invalidCases = [
            ['when@true' => ['error' => ['foo' => 'bar']]], // <-- Invalid keys
            ['when@true' => ['error' => ['message' => false]]], // <-- Invalid message type
            ['when@true' => ['error' => ['exceptionClass' => false]]], // <-- Invalid exceptionClass type
            ['when@true' => ['error' => ['context' => 'nope']]], // <-- Invalid context type
        ];

        foreach ($invalidCases as $invalidCase) {
            $definition = [
                'entrypoint' => [...$decisionNode, ...$invalidCase],
            ];
            expect(fn () => $factory->create($definition))->toThrow(InvalidOptionsException::class);
        }
    });

    it('denies invalid `goto` structure and types', function () use ($factory) {
        $decisionNode = [
            'checker' => 'checker.default',
            'when@false' => ['end' => false],
        ];

        $invalidCases = [
            ['when@true' => ['goto' => ['foo' => 'bar']]], // <-- Invalid keys
            ['when@true' => ['goto' => ['id' => 123]]], // <-- Invalid id type
            ['when@true' => ['goto' => ['id' => 'root', 'context' => 'nope']]], // <-- Invalid context type
        ];

        foreach ($invalidCases as $invalidCase) {
            $definition = [
                'entrypoint' => [...$decisionNode, ...$invalidCase],
            ];
            expect(fn () => $factory->create($definition))->toThrow(InvalidOptionsException::class);
        }
    });

    it('accepts fully handled actions when checking unhandled steps', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'checker.default',
                'when@true' => ['end' => true],
                'when@false' => ['error' => 'Oops'],
            ],
        ];

        // Should not throw since both branches are actions (no unhandled steps)
        $factory->create($definition, ['allowUnhandledCases' => false]);

        expect(true)->toBeTrue();
    });

    it('accepts a boolean shortcut for `when@true` or `when@false`', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'checker.default',
                'when@true' => true,
                'when@false' => false,
            ],
        ];

        $flowchart = $factory->create($definition);

        expect($flowchart->entrypoint->cases->get(true))->toBeInstanceOf(EndFlow::class)
            ->and($flowchart->entrypoint->cases->get(true))->toEqual(new EndFlow(true))
            ->and($flowchart->entrypoint->cases->get(false))->toBeInstanceOf(EndFlow::class)
            ->and($flowchart->entrypoint->cases->get(false))->toEqual(new EndFlow(false))
        ;
    });

    it('complains when trying to use a node that does not exist', function () {
        $content = <<<YAML
entrypoint:
    use: unknown_node
YAML;

        expect(fn () => new FlowchartFactory()->create(Yaml::parse($content)))
            ->toThrow(FlowchartBuildException::class, 'Block `unknown_node` not found.');
    });
});
