<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Concerns;

/**
 * @mixin \Cortex\JsonRepair\JsonRepairer
 */
trait OutputTracking
{
    private ?string $lastNonWhitespaceChar = null;

    private int $outputSyncedLength = 0;

    private function resetOutputTracking(): void
    {
        $this->lastNonWhitespaceChar = null;
        $this->outputSyncedLength = 0;
    }

    private function syncOutputTail(): void
    {
        $length = strlen($this->output);

        if ($length === $this->outputSyncedLength) {
            return;
        }

        $trimmed = rtrim(substr($this->output, $this->outputSyncedLength), " \t\n\r\f\v");

        if ($trimmed !== '') {
            $this->lastNonWhitespaceChar = $trimmed[strlen($trimmed) - 1];
        }

        $this->outputSyncedLength = $length;
    }

    private function recalculateLastNonWhitespaceChar(): void
    {
        $trimmed = rtrim($this->output);
        $this->lastNonWhitespaceChar = $trimmed === '' ? null : $trimmed[strlen($trimmed) - 1];
        $this->outputSyncedLength = strlen($this->output);
    }

    private function outputEndsWithNonWhitespace(string $char): bool
    {
        $this->syncOutputTail();

        return $this->lastNonWhitespaceChar === $char;
    }

    private function trimOutputTrailingWhitespace(): void
    {
        if ($this->output === '') {
            return;
        }

        $lastChar = $this->output[strlen($this->output) - 1];

        if (in_array($lastChar, [' ', "\t", "\n", "\r", "\f", "\v"], true)) {
            $this->output = rtrim($this->output);
            $this->recalculateLastNonWhitespaceChar();
        }
    }

    private function truncateOutput(int $length): void
    {
        $this->output = substr($this->output, 0, $length);
        $this->recalculateLastNonWhitespaceChar();
    }

    private function setOutput(string $output): void
    {
        $this->output = $output;
        $this->recalculateLastNonWhitespaceChar();
    }
}
