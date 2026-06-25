<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

final readonly class RepairResult
{
    /**
     * @param list<string> $fixes
     */
    public function __construct(
        public string $json,
        public bool $wasAlreadyValid,
        public array $fixes,
    ) {}
}
