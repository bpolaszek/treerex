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
                'when@no' => ['end' => $end],
                'when@yes' => [
                    'checker' => 'some.other.checker.service',
                    'when@yes' => ['goto' => $goto],
                    'when@no' => [
                        'checker' => 'yet.another.checker.service',
                        'when@no' => ['error' => $error],
                    ],
                ],
            ],
        ];
        $flowchart = $factory->create($flowchartDefinition);
        expect([...$flowchart->context])->toBe(['foo' => 'bar'])
            ->and($flowchart->entrypoint->checkerServiceId)->toBe('some.checker.service')
            ->and($flowchart->entrypoint->id)->toBe('root')
            ->and($flowchart->entrypoint->label)->toBe('Root node')
            ->and($flowchart->entrypoint->whenNo)->toBeInstanceOf(EndFlow::class)
            ->and($flowchart->entrypoint->whenNo->result)->toBeFalse()
            ->and($flowchart->entrypoint->whenYes)->toBeInstanceOf(DecisionNode::class)
            ->and($flowchart->entrypoint->whenYes->checkerServiceId)->toBe('some.other.checker.service')
            ->and($flowchart->entrypoint->whenYes->whenYes)->toBeInstanceOf(GotoNode::class)
            ->and($flowchart->entrypoint->whenYes->whenYes->id)->toBe('root')
            ->and($flowchart->entrypoint->whenYes->whenNo)->toBeInstanceOf(DecisionNode::class)
            ->and($flowchart->entrypoint->whenYes->whenNo->checkerServiceId)->toBe('yet.another.checker.service')
            ->and($flowchart->entrypoint->whenYes->whenNo->whenNo)->toBeInstanceOf(RaiseError::class)
            ->and($flowchart->entrypoint->whenYes->whenNo->whenNo->message)->toBe('Ooops')
            ->and($flowchart->entrypoint->whenYes->whenNo->whenYes)->toBeInstanceOf(UnhandledStep::class);
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
                'when@no' => ['end' => false],
                'when@yes' => [
                    'checker' => 'some.other.checker.service',
                    'when@yes' => ['goto' => 'root'],
                    'when@no' => [
                        'checker' => 'yet.another.checker.service',
                        'when@no' => ['error' => 'Ooops'],
                    ],
                ],
            ],
        ];
        expect(fn () => $factory->create($flowchartDefinition, allowUnhandledSteps: false))
            ->toThrow(FlowchartBuildException::class);
    });
});

describe('Flowchart Factory Validation', function () {
    $factory = new FlowchartFactory();

    it('complains when a node contains several actions', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'checker.default',
                'when@yes' => ['end' => true, 'goto' => 'root'],
                'when@no' => ['end' => false],
            ],
        ];

        expect(fn () => $factory->create($definition))->toThrow(InvalidOptionsException::class);
    });

    it('denies invalid `end` keys', function () use ($factory) {
        $decisionNode = [
            'checker' => 'checker.default',
        ];

        $invalidCases = [
            ['when@yes' => ['end' => ['foo' => 'bar']]], // <-- Invalid keys in end definition
            ['when@yes' => ['end' => ['result' => 'nope']]], // <-- Invalid type for end[result]
            ['when@yes' => ['end' => ['context' => 'nope']]], // <-- Invalid type for end[context]
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
            'when@no' => ['end' => false],
        ];

        $invalidCases = [
            ['when@yes' => ['error' => ['foo' => 'bar']]], // <-- Invalid keys
            ['when@yes' => ['error' => ['message' => false]]], // <-- Invalid message type
            ['when@yes' => ['error' => ['exceptionClass' => false]]], // <-- Invalid exceptionClass type
            ['when@yes' => ['error' => ['context' => 'nope']]], // <-- Invalid context type
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
            'when@no' => ['end' => false],
        ];

        $invalidCases = [
            ['when@yes' => ['goto' => ['foo' => 'bar']]], // <-- Invalid keys
            ['when@yes' => ['goto' => ['id' => 123]]], // <-- Invalid id type
            ['when@yes' => ['goto' => ['id' => 'root', 'context' => 'nope']]], // <-- Invalid context type
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
                'when@yes' => ['end' => true],
                'when@no' => ['error' => 'Oops'],
            ],
        ];

        // Should not throw since both branches are actions (no unhandled steps)
        $factory->create($definition, allowUnhandledSteps: false);

        expect(true)->toBeTrue();
    });

    it('accepts a boolean shortcut for `when@true` or `when@false`', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'checker.default',
                'when@yes' => true,
                'when@no' => false,
            ],
        ];

        $flowchart = $factory->create($definition);

        expect($flowchart->entrypoint->whenYes)->toBeInstanceOf(EndFlow::class)
            ->and($flowchart->entrypoint->whenYes)->toEqual(new EndFlow(true))
            ->and($flowchart->entrypoint->whenNo)->toBeInstanceOf(EndFlow::class)
            ->and($flowchart->entrypoint->whenNo)->toEqual(new EndFlow(false))
        ;
    });
});
