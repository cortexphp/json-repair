<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Concerns;

/**
 * @mixin \Cortex\JsonRepair\JsonRepairer
 */
trait RepairLogging
{
    /**
     * Log a repair action with context.
     *
     * @param string $message Description of the repair action
     * @param array<string, mixed> $context Additional context data
     */
    private function log(string $message, array $context = []): void
    {
        $this->logger?->debug($message, array_merge([
            'position' => $this->pos,
            'context' => $this->getContextSnippet(),
        ], $context));
    }

    /**
     * Get a snippet of the JSON around the current position for logging context.
     */
    private function getContextSnippet(int $window = 15): string
    {
        $start = max(0, $this->pos - $window);
        $end = min(strlen($this->json), $this->pos + $window);

        $before = substr($this->json, $start, $this->pos - $start);
        $after = substr($this->json, $this->pos, $end - $this->pos);

        return $before . '>>>' . $after;
    }
}
