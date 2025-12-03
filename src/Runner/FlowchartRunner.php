<?php

declare(strict_types=1);

namespace BenTools\TreeRex\Runner;

use BenTools\TreeRex\Action\Action;
use BenTools\TreeRex\Checker\CheckerInterface;
use BenTools\TreeRex\Checker\ExpressionLanguageChecker;
use BenTools\TreeRex\Definition\DecisionNode;
use BenTools\TreeRex\Definition\Flowchart;
use BenTools\TreeRex\Exception\FlowchartRuntimeException;
use BenTools\TreeRex\Exception\SkippedSteps;
use BenTools\TreeRex\Utils\ServiceLocator;
use Exception;
use Psr\Container\ContainerInterface;

use function is_array;

final readonly class FlowchartRunner implements FlowchartRunnerInterface
{
    public function __construct(
        private ContainerInterface $serviceLocator = new ServiceLocator([
            ExpressionLanguageChecker::class => new ExpressionLanguageChecker(),
        ]),
    ) {
    }

    public function satisfies(
        mixed $subject,
        Flowchart|string $flowchart,
        RunnerContext|array $context = new RunnerContext(),
    ): bool|int|string {
        $context = is_array($context) ? new RunnerContext($context) : $context;
        $flowchart = $flowchart instanceof Flowchart ? $flowchart : $this->resolveFlowchart($flowchart);

        $decisionNode = $flowchart->entrypoint;
        $service = $this->resolveCheckerService($decisionNode->checkerServiceId);

        $state = new RunnerState($decisionNode, $subject, $flowchart, $service, $context)
            ->withAppendedContext($flowchart->context);

        return $this->process($state);
    }

    /**
     * @throws SkippedSteps
     */
    private function process(RunnerState $state): bool|int|string
    {
        $decisionNode = $state->decisionNode;
        try {
            $lastResult = $state->checker->satisfies($state->subject, $decisionNode->criteria, $state->context);
        } catch (Exception $e) {
            throw new FlowchartRuntimeException($state, $e->getMessage(), previous: $e);
        }
        $state = $state->withLastResult($lastResult, $decisionNode);

        $next = $decisionNode->whenResultIs($lastResult);

        if ($next instanceof DecisionNode) {
            $state = $state->with(decisionNode: $next, checker: $this->resolveCheckerService($next->checkerServiceId));
        }

        try {
            return match (true) {
                $next instanceof DecisionNode => $this->process($state),
                $next instanceof Action => $next($state),
            };
        } catch (SkippedSteps $e) {
            $next = $state->flowchart->findDecisionNodeById($e->decisionNodeId)
                ?? throw new FlowchartRuntimeException($state, "Id `{$e->decisionNodeId}` not found.");

            return $this->process($state->with($next, checker: $this->resolveCheckerService($next->checkerServiceId)));
        }
    }

    private function resolveFlowchart(string $flowchartServiceId): Flowchart
    {
        return $this->serviceLocator->get($flowchartServiceId); // @phpstan-ignore return.type
    }

    private function resolveCheckerService(string $checkerServiceId): CheckerInterface
    {
        return $this->serviceLocator->get($checkerServiceId); // @phpstan-ignore return.type
    }
}
