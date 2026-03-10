<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Tests\Integration;

use BenTools\TreeRex\Action\WithSideEffect;
use BenTools\TreeRex\Checker\CheckerInterface;
use BenTools\TreeRex\Exception\SideEffectException;
use BenTools\TreeRex\Factory\FlowchartFactory;
use BenTools\TreeRex\Runner\FlowchartRunner;
use BenTools\TreeRex\Runner\RunnerContext;
use BenTools\TreeRex\Runner\RunnerState;
use BenTools\TreeRex\SideEffect\SideEffectInterface;
use BenTools\TreeRex\SideEffect\SideEffectTiming;
use BenTools\TreeRex\Utils\ServiceLocator;
use Exception;
use RuntimeException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Yaml\Yaml;

use function expect;

describe('Side Effect Feature', function () {
    it('executes a side-effect before end (simple string form)', function () {
        $log = [];

        $checker = new class implements CheckerInterface {
            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                return true;
            }
        };

        $sideEffect = new class($log) implements SideEffectInterface {
            public function __construct(private array &$log)
            {
            }

            public function execute(RunnerState $state): void
            {
                $this->log[] = 'side-effect executed';
            }
        };

        $definition = Yaml::parse(<<<'YAML'
entrypoint:
    checker: default
    when@true:
        end: true
        sideEffect: my_side_effect
    when@false:
        end: false
YAML);

        $flowchart = new FlowchartFactory()->create($definition);
        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
            'my_side_effect' => $sideEffect,
        ]));

        $result = $runner->satisfies('anything', $flowchart);

        expect($result)->toBeTrue()
            ->and($log)->toBe(['side-effect executed']);
    });

    it('executes a side-effect with extended form (timing: after)', function () {
        $log = [];

        $checker = new class($log) implements CheckerInterface {
            public function __construct(private array &$log)
            {
            }

            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                $this->log[] = 'checker executed';

                return true;
            }
        };

        $sideEffect = new class($log) implements SideEffectInterface {
            public function __construct(private array &$log)
            {
            }

            public function execute(RunnerState $state): void
            {
                $this->log[] = 'side-effect executed';
            }
        };

        $definition = Yaml::parse(<<<'YAML'
entrypoint:
    checker: default
    when@true:
        end: true
        sideEffect:
            id: my_side_effect
            timing: after
    when@false:
        end: false
YAML);

        $flowchart = new FlowchartFactory()->create($definition);
        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
            'my_side_effect' => $sideEffect,
        ]));

        $result = $runner->satisfies('anything', $flowchart);

        expect($result)->toBeTrue()
            ->and($log)->toBe(['checker executed', 'side-effect executed']);
    });

    it('enriches context via side-effect', function () {
        $checker = new class implements CheckerInterface {
            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                return true;
            }
        };

        $sideEffect = new class implements SideEffectInterface {
            public function execute(RunnerState $state): void
            {
                $state->context['enriched'] = 'by side-effect';
            }
        };

        $definition = Yaml::parse(<<<'YAML'
entrypoint:
    checker: default
    when@true:
        end: true
        sideEffect: enricher
    when@false:
        end: false
YAML);

        $flowchart = new FlowchartFactory()->create($definition);
        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
            'enricher' => $sideEffect,
        ]));

        $context = new RunnerContext();
        $runner->satisfies('anything', $flowchart, $context);

        expect($context['enriched'])->toBe('by side-effect');
    });

    it('supports side-effect with context enrichment from YAML', function () {
        $checker = new class implements CheckerInterface {
            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                return true;
            }
        };

        $sideEffect = new class implements SideEffectInterface {
            public function execute(RunnerState $state): void
            {
                // no-op, context is enriched by the YAML definition
            }
        };

        $definition = Yaml::parse(<<<'YAML'
entrypoint:
    checker: default
    when@true:
        end: true
        sideEffect:
            id: noop
            context:
                fromYaml: enriched
    when@false:
        end: false
YAML);

        $flowchart = new FlowchartFactory()->create($definition);
        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
            'noop' => $sideEffect,
        ]));

        $context = new RunnerContext();
        $runner->satisfies('anything', $flowchart, $context);

        expect($context['fromYaml'])->toBe('enriched');
    });

    it('supports side-effect with goto action', function () {
        $log = [];

        $checker = new class implements CheckerInterface {
            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                return true;
            }
        };

        $sideEffect = new class($log) implements SideEffectInterface {
            public function __construct(private array &$log)
            {
            }

            public function execute(RunnerState $state): void
            {
                $this->log[] = 'side-effect before goto';
            }
        };

        $definition = Yaml::parse(<<<'YAML'
entrypoint:
    checker: default
    id: first
    when@true:
        goto: second
        sideEffect: my_side_effect
    when@false:
        id: second
        checker: default
        when@true:
            end: true
        when@false:
            end: false
YAML);

        $flowchart = new FlowchartFactory()->create($definition);
        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
            'my_side_effect' => $sideEffect,
        ]));

        $result = $runner->satisfies('anything', $flowchart);

        expect($result)->toBeTrue()
            ->and($log)->toBe(['side-effect before goto']);
    });

    it('wraps side-effect exceptions in SideEffectException', function () {
        $checker = new class implements CheckerInterface {
            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                return true;
            }
        };

        $sideEffect = new class implements SideEffectInterface {
            public function execute(RunnerState $state): void
            {
                throw new RuntimeException('Something went wrong in side-effect');
            }
        };

        $definition = Yaml::parse(<<<'YAML'
entrypoint:
    checker: default
    when@true:
        end: true
        sideEffect: failing
    when@false:
        end: false
YAML);

        $flowchart = new FlowchartFactory()->create($definition);
        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
            'failing' => $sideEffect,
        ]));

        expect(fn () => $runner->satisfies('anything', $flowchart))
            ->toThrow(SideEffectException::class, 'Something went wrong in side-effect');
    });

    it('supports string end results', function () {
        $checker = new class implements CheckerInterface {
            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                return true;
            }
        };

        $definition = Yaml::parse(<<<'YAML'
entrypoint:
    checker: default
    when@true:
        end: HIT
    when@false:
        end: MISS
YAML);

        $flowchart = new FlowchartFactory()->create($definition);
        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
        ]));

        $result = $runner->satisfies('anything', $flowchart);

        expect($result)->toBe('HIT');
    });

    it('supports string end results in array form', function () {
        $checker = new class implements CheckerInterface {
            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                return false;
            }
        };

        $definition = Yaml::parse(<<<'YAML'
entrypoint:
    checker: default
    when@true:
        end: HIT
    when@false:
        end:
            result: BYPASS
            context:
                reason: not cacheable
YAML);

        $flowchart = new FlowchartFactory()->create($definition);
        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
        ]));

        $context = new RunnerContext();
        $result = $runner->satisfies('anything', $flowchart, $context);

        expect($result)->toBe('BYPASS')
            ->and($context['reason'])->toBe('not cacheable');
    });
});

describe('Side Effect Factory Validation', function () {
    $factory = new FlowchartFactory();

    it('denies sideEffect without an action', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'default',
                'when@true' => ['sideEffect' => 'some_service'],
                'when@false' => ['end' => false],
            ],
        ];

        // sideEffect alone (no end/goto/error) is treated as a DecisionNode definition,
        // which will fail because there's no checker or when@ cases
        expect(fn () => $factory->create($definition))->toThrow(Exception::class);
    });

    it('denies invalid sideEffect keys', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'default',
                'when@true' => [
                    'end' => true,
                    'sideEffect' => ['foo' => 'bar'],
                ],
                'when@false' => ['end' => false],
            ],
        ];

        expect(fn () => $factory->create($definition))
            ->toThrow(InvalidOptionsException::class);
    });

    it('denies invalid sideEffect timing', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'default',
                'when@true' => [
                    'end' => true,
                    'sideEffect' => ['id' => 'some_service', 'timing' => 'during'],
                ],
                'when@false' => ['end' => false],
            ],
        ];

        expect(fn () => $factory->create($definition))
            ->toThrow(InvalidOptionsException::class);
    });

    it('denies sideEffect array form without id', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'default',
                'when@true' => [
                    'end' => true,
                    'sideEffect' => ['timing' => 'before'],
                ],
                'when@false' => ['end' => false],
            ],
        ];

        expect(fn () => $factory->create($definition))
            ->toThrow(InvalidOptionsException::class);
    });

    it('denies invalid sideEffect context type', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'default',
                'when@true' => [
                    'end' => true,
                    'sideEffect' => ['id' => 'some_service', 'context' => 'nope'],
                ],
                'when@false' => ['end' => false],
            ],
        ];

        expect(fn () => $factory->create($definition))
            ->toThrow(InvalidOptionsException::class);
    });

    it('creates WithSideEffect action with simple string form', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'default',
                'when@true' => [
                    'end' => true,
                    'sideEffect' => 'my_service',
                ],
                'when@false' => ['end' => false],
            ],
        ];

        $flowchart = $factory->create($definition);
        $action = $flowchart->entrypoint->whenResultIs(true);

        expect($action)->toBeInstanceOf(WithSideEffect::class)
            ->and($action->sideEffectServiceId)->toBe('my_service')
            ->and($action->timing)->toBe(SideEffectTiming::BEFORE);
    });

    it('creates WithSideEffect action with extended form', function () use ($factory) {
        $definition = [
            'entrypoint' => [
                'checker' => 'default',
                'when@true' => [
                    'end' => true,
                    'sideEffect' => [
                        'id' => 'my_service',
                        'timing' => 'after',
                        'context' => ['key' => 'value'],
                    ],
                ],
                'when@false' => ['end' => false],
            ],
        ];

        $flowchart = $factory->create($definition);
        $action = $flowchart->entrypoint->whenResultIs(true);

        expect($action)->toBeInstanceOf(WithSideEffect::class)
            ->and($action->sideEffectServiceId)->toBe('my_service')
            ->and($action->timing)->toBe(SideEffectTiming::AFTER)
            ->and($action->context)->toBe(['key' => 'value']);
    });
});
