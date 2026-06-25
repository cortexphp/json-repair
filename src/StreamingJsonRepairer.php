<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;

final class StreamingJsonRepairer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private string $buffer = '';

    public function __construct(
        private readonly bool $ensureAscii = true,
        private readonly bool $omitEmptyValues = false,
        private readonly bool $omitIncompleteStrings = false,
        private readonly ?DuplicateKeyPolicy $duplicateKeyPolicy = null,
    ) {}

    public function feed(string $chunk): self
    {
        $this->buffer .= $chunk;

        return $this;
    }

    public function current(): string
    {
        $jsonRepairer = new JsonRepairer(
            $this->buffer,
            $this->ensureAscii,
            $this->omitEmptyValues,
            $this->omitIncompleteStrings,
            $this->duplicateKeyPolicy,
        );

        if ($this->logger instanceof LoggerInterface) {
            $jsonRepairer->setLogger($this->logger);
        }

        return $jsonRepairer->repair();
    }

    public function reset(): self
    {
        $this->buffer = '';

        return $this;
    }
}
