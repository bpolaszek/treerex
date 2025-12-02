<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Tests\Integration;

use BenTools\TreeRex\Checker\CheckerInterface;
use BenTools\TreeRex\Checker\ExpressionLanguageChecker;
use BenTools\TreeRex\Exception\FlowchartRuntimeException;
use BenTools\TreeRex\Exception\UnhandledStepException;
use BenTools\TreeRex\Factory\FlowchartFactory;
use BenTools\TreeRex\Runner\FlowchartRunner;
use BenTools\TreeRex\Runner\RunnerContext;
use BenTools\TreeRex\Tests\Integration\Subject\Product;
use BenTools\TreeRex\Tests\Integration\Subject\User;
use BenTools\TreeRex\Utils\ServiceLocator;
use RuntimeException;
use stdClass;
use Symfony\Component\Yaml\Yaml;
use Throwable;

use function expect;

$definition = <<<YAML
entrypoint:
    id: stock_check
    label: Ensure product is in stock 
    checker: default
    criteria: product.stock > 0
    when@false:
        end: 
            result: false
            context:
                reason: Out of stock
    when@true:
        id: blacklist_check
        label: Ensure product is allowed to be purchased    
        checker: default
        criteria: "product.blacklisted"
        when@true:
            id: role_check
            checker: default
            criteria: context.user.role === 'ADMIN'
            when@false:
                end: 
                    result: false
                    context:
                        reason: Product is blacklisted
            when@true:
                goto: category_check
        when@false:
            use: category_check # <-- Demonstrates reusable blocks ✨


blocks:
    category_check:
        label: Ensure product is categorized 
        checker: default
        criteria: product.categorized
        when@false:
            error: Product should never be uncategorized
        when@true:
            id: expiration_check
            label: Ensure product is not expired 
            checker: default
            criteria: "!product.expired"
            when@true:
                end: true
            #when@false # <-- this step has not been configured 😬
YAML;

describe('Flowchart Runner Test', function () use ($definition) {
    $flowchart = new FlowchartFactory()->create(Yaml::parse($definition));
    $runner = new FlowchartRunner(new ServiceLocator([
        'default' => new ExpressionLanguageChecker('product'),
    ]));

    it('is sellable', function () use ($flowchart, $runner) {
        $product = new Product(stock: 10, blacklisted: false, categorized: true);
        $user = new User('GUEST');
        $context = new RunnerContext(['user' => $user]);
        $sellable = $runner->satisfies($product, $flowchart, $context);

        expect($sellable)->toBeTrue()
            ->and($context->state->decisionNode->id)->toBe('expiration_check')
            ->and($context->state->history)->toBe([
                ['stock_check', true],
                ['blacklist_check', false],
                ['category_check', true],
                ['expiration_check', true],
            ])
        ;
    });

    it('is not sellable because it is not in stock', function () use ($flowchart, $runner) {
        $product = new Product(stock: 0, blacklisted: false, categorized: true);
        $user = new User('GUEST');
        $context = new RunnerContext(['user' => $user]);
        $sellable = $runner->satisfies($product, $flowchart, $context);

        expect($sellable)->toBeFalse()
            ->and($context->state->decisionNode->id)->toBe('stock_check')
            ->and($context->state->history)->toBe([
                ['stock_check', false],
            ])
            ->and($context['reason'])->toBe('Out of stock')
        ;
    });

    it('is not sellable because it is blacklisted', function () use ($flowchart, $runner) {
        $product = new Product(stock: 10, blacklisted: true, categorized: true);
        $user = new User('GUEST');
        $context = new RunnerContext(['user' => $user]);
        $sellable = $runner->satisfies($product, $flowchart, $context);

        expect($sellable)->toBeFalse()
            ->and($context->state->decisionNode->id)->toBe('role_check')
            ->and($context->state->history)->toBe([
                ['stock_check', true],
                ['blacklist_check', true],
                ['role_check', false],
            ])
            ->and($context['reason'])->toBe('Product is blacklisted')
        ;
    });

    it('is sellable because it is blacklisted but user is ADMIN', function () use ($flowchart, $runner) {
        $product = new Product(stock: 10, blacklisted: true, categorized: true);
        $user = new User('ADMIN');
        $context = new RunnerContext(['user' => $user]);
        $sellable = $runner->satisfies($product, $flowchart, $context);

        expect($sellable)->toBeTrue()
            ->and($context->state->decisionNode->id)->toBe('expiration_check')
            ->and($context->state->history)->toBe([
                ['stock_check', true],
                ['blacklist_check', true],
                ['role_check', true],
                ['category_check', true],
                ['expiration_check', true],
            ])
        ;
    });

    it('throws an error whenever a product is uncategorized', function () use ($flowchart, $runner) {
        $product = new Product(stock: 10, blacklisted: false, categorized: false);
        $user = new User('ADMIN');
        $context = new RunnerContext(['user' => $user]);
        expect(fn () => $runner->satisfies($product, $flowchart, $context))
            ->toThrow(RuntimeException::class, 'Product should never be uncategorized')
            ->and($context->state->history)->toBe([
                ['stock_check', true],
                ['blacklist_check', false],
                ['category_check', false],
            ])
        ;
    });

    it('throws an error whenever a step is unhandled', function () use ($flowchart, $runner) {
        $product = new Product(stock: 10, blacklisted: false, categorized: true, expired: true);
        $user = new User('ADMIN');
        $context = new RunnerContext(['user' => $user]);
        expect(fn () => $runner->satisfies($product, $flowchart, $context))
            ->toThrow(UnhandledStepException::class)
            ->and($context->state->history)->toBe([
                ['stock_check', true],
                ['blacklist_check', false],
                ['category_check', true],
            ])
        ;
    });
});

describe('Flowchart Anomalies', function () {
    it('throws an error when checker fails', function () {
        $checker = new class implements CheckerInterface {
            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                throw new RuntimeException('💥');
            }
        };

        $flowchart = new FlowchartFactory()->create([
            'entrypoint' => [
                'checker' => 'default',
            ],
        ]);

        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
        ]));
        $subject = new stdClass();
        $context = new RunnerContext([]);
        expect(fn () => $runner->satisfies($subject, $flowchart, $context))
            ->toThrow(FlowchartRuntimeException::class, '💥');
    });

    it('throws an error when trying to jump to an unknown node', function () {
        $checker = new class implements CheckerInterface {
            public function satisfies(mixed $subject, mixed $criteria, RunnerContext $context): bool
            {
                return true;
            }
        };

        $flowchart = new FlowchartFactory()->create([
            'entrypoint' => [
                'checker' => 'default',
                'when@true' => [
                    'goto' => 'neverland',
                ],
            ],
        ]);

        $runner = new FlowchartRunner(new ServiceLocator([
            'default' => $checker,
        ]));
        $subject = new stdClass();
        $context = new RunnerContext([]);
        expect(fn () => $runner->satisfies($subject, $flowchart, $context))
            ->toThrow(FlowchartRuntimeException::class, 'Id `neverland` not found.');
    });
});

it('fetches a FLowchart from the service container', function () {
    $definition = <<<YAML
entrypoint:
    checker: checker.default
    criteria: product.stock > 0
    when@true: 
        end: true
YAML;

    $runner = new FlowchartRunner(new ServiceLocator([
        'flowchart.acme' => new FlowchartFactory()->create(Yaml::parse($definition)),
        'checker.default' => new ExpressionLanguageChecker('product'),
    ]));

    expect(fn () => $runner->satisfies(new Product(10, false, true), 'flowchart.acme'))
        ->not()->toThrow(Throwable::class);
});
