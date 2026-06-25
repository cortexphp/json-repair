<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

/**
 * Parser state constants shared by {@see JsonRepairer} and its state-machine concerns.
 *
 * Defined on an interface so they can be referenced explicitly (e.g. ParserState::STATE_START)
 * from within traits, where constants declared on the consuming class are not resolvable.
 */
interface ParserState
{
    public const int STATE_START = 0;

    public const int STATE_IN_STRING = 1;

    public const int STATE_IN_STRING_ESCAPE = 2;

    public const int STATE_IN_NUMBER = 3;

    public const int STATE_IN_OBJECT_KEY = 4;

    public const int STATE_IN_OBJECT_VALUE = 5;

    public const int STATE_IN_ARRAY = 6;

    public const int STATE_EXPECTING_COLON = 7;

    public const int STATE_EXPECTING_COMMA_OR_END = 8;
}
