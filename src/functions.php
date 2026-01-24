<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

/**
 * Repair a broken JSON string.
 *
 * @param string $json The JSON string to repair
 * @param bool $ensureAscii Whether to escape non-ASCII characters (default: true)
 * @return string The repaired JSON string
 */
function json_repair(string $json, bool $ensureAscii = true): string
{
    $repairer = new JsonRepair($json, $ensureAscii);

    return $repairer->repair();
}
