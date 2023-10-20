<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\SupportStrategy\WorkflowSupportStrategyInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Registry
{
    private array $workflows = [];

    public function addWorkflow(WorkflowInterface $workflow, WorkflowSupportStrategyInterface $supportStrategy): void
    {
        $this->workflows[] = [$workflow, $supportStrategy];
    }

    public function has(object $subject, string $workflowName = null): bool
    {
        foreach ($this->workflows as [$workflow, $supportStrategy]) {
            if ($this->supports($workflow, $supportStrategy, $subject, $workflowName)) {
                return true;
            }
        }

        return false;
    }

    public function get(object $subject, string $workflowName = null): WorkflowInterface
    {
        $matched = [];

        foreach ($this->workflows as [$workflow, $supportStrategy]) {
            if ($this->supports($workflow, $supportStrategy, $subject, $workflowName)) {
                $matched[] = $workflow;
            }
        }

        if (!$matched) {
            throw new InvalidArgumentException(sprintf('Unable to find a workflow for class "%s".', get_debug_type($subject)));
        }

        if (2 <= \count($matched)) {
            $names = array_map(static fn (WorkflowInterface $workflow): string => $workflow->getName(), $matched);

            throw new InvalidArgumentException(sprintf('Too many workflows (%s) match this subject (%s); set a different name on each and use the second (name) argument of this method.', implode(', ', $names), get_debug_type($subject)));
        }

        return $matched[0];
    }

    /**
     * @return Workflow[]
     */
    public function all(object $subject): array
    {
        $matched = [];
        foreach ($this->workflows as [$workflow, $supportStrategy]) {
            if ($supportStrategy->supports($workflow, $subject)) {
                $matched[] = $workflow;
            }
        }

        return $matched;
    }

    private function supports(WorkflowInterface $workflow, WorkflowSupportStrategyInterface $supportStrategy, object $subject, ?string $workflowName): bool
    {
        if (null !== $workflowName && $workflowName !== $workflow->getName()) {
            return false;
        }

        return $supportStrategy->supports($workflow, $subject);
    }
}
