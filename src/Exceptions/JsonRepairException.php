<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Exceptions;

use RuntimeException;

class JsonRepairException extends RuntimeException
{
    /**
     * Create a new exception for invalid JSON after repair.
     *
     * This exception is thrown when the repair process completes but the
     * resulting output is still not valid JSON.
     *
     * @param string $json The repaired JSON that is still invalid
     *
     * @return self A new JsonRepairException instance
     */
    public static function invalidJsonAfterRepair(string $json): self
    {
        return new self(
            sprintf(
                'JSON repair completed but the result is still invalid JSON: %s',
                $json,
            ),
        );
    }
}
