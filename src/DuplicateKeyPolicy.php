<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

enum DuplicateKeyPolicy
{
    case KeepFirst;
    case KeepLast;
}
