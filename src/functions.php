<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

use Psr\Log\LoggerInterface;

/**
 * Repair a broken JSON string.
 *
 * @param string $json The JSON string to repair
 * @param bool $ensureAscii Whether to escape non-ASCII characters (default: true)
 * @param bool $omitEmptyValues Whether to remove keys with missing values instead of adding empty strings (default: false)
 * @param bool $omitIncompleteStrings Whether to remove keys with incomplete string values instead of closing them (default: false)
 * @param \Cortex\JsonRepair\DuplicateKeyPolicy|null $duplicateKeyPolicy How to handle duplicate object keys (default: null — no deduplication)
 * @param \Psr\Log\LoggerInterface|null $logger Optional PSR-3 logger for debugging repair actions
 *
 * @return string The repaired JSON string
 */
function json_repair(
    string $json,
    bool $ensureAscii = true,
    bool $omitEmptyValues = false,
    bool $omitIncompleteStrings = false,
    ?DuplicateKeyPolicy $duplicateKeyPolicy = null,
    ?LoggerInterface $logger = null,
): string {
    $repairer = new JsonRepairer($json, $ensureAscii, $omitEmptyValues, $omitIncompleteStrings, $duplicateKeyPolicy);

    if ($logger instanceof LoggerInterface) {
        $repairer->setLogger($logger);
    }

    return $repairer->repair();
}

/**
 * Repair and decode a broken JSON string.
 *
 * @param string $json The JSON string to repair and decode
 * @param int<1, max> $depth Maximum nesting depth of the structure being decoded
 * @param int $flags Bitmask of JSON decode flags (default: JSON_THROW_ON_ERROR)
 * @param bool $ensureAscii Whether to escape non-ASCII characters (default: true)
 * @param bool $omitEmptyValues Whether to remove keys with missing values instead of adding empty strings (default: false)
 * @param bool $omitIncompleteStrings Whether to remove keys with incomplete string values instead of closing them (default: false)
 * @param \Cortex\JsonRepair\DuplicateKeyPolicy|null $duplicateKeyPolicy How to handle duplicate object keys (default: null — no deduplication)
 * @param \Psr\Log\LoggerInterface|null $logger Optional PSR-3 logger for debugging repair actions
 *
 * @return mixed The decoded JSON data
 */
function json_repair_decode(
    string $json,
    int $depth = 512,
    int $flags = JSON_THROW_ON_ERROR,
    bool $ensureAscii = true,
    bool $omitEmptyValues = false,
    bool $omitIncompleteStrings = false,
    ?DuplicateKeyPolicy $duplicateKeyPolicy = null,
    ?LoggerInterface $logger = null,
): mixed {
    $repairer = new JsonRepairer($json, $ensureAscii, $omitEmptyValues, $omitIncompleteStrings, $duplicateKeyPolicy);

    if ($logger instanceof LoggerInterface) {
        $repairer->setLogger($logger);
    }

    return $repairer->decode($depth, $flags);
}
