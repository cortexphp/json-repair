<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Cortex\JsonRepair\Concerns\StateMachine;
use Cortex\JsonRepair\Concerns\RepairLogging;
use Cortex\JsonRepair\Concerns\StringHeuristics;
use Cortex\JsonRepair\Concerns\InputSanitization;
use Cortex\JsonRepair\Exceptions\JsonRepairException;

class JsonRepairer implements LoggerAwareInterface
{
    use LoggerAwareTrait;
    use StateMachine;
    use RepairLogging;
    use StringHeuristics;
    use InputSanitization;

    private const int STATE_START = 0;

    private const int STATE_IN_STRING = 1;

    private const int STATE_IN_STRING_ESCAPE = 2;

    private const int STATE_IN_NUMBER = 3;

    private const int STATE_IN_OBJECT_KEY = 4;

    private const int STATE_IN_OBJECT_VALUE = 5;

    private const int STATE_IN_ARRAY = 6;

    private const int STATE_EXPECTING_COLON = 7;

    private const int STATE_EXPECTING_COMMA_OR_END = 8;

    private int $state = self::STATE_START;

    private int $pos = 0;

    private string $output = '';

    /**
     * @var array<string>
     */
    private array $stack = [];

    private bool $inString = false;

    private string $stringDelimiter = '';

    private int $stateBeforeString = self::STATE_START;

    private int $currentKeyStart = -1;

    /**
     * @param string $json The JSON string to repair
     * @param bool $ensureAscii Whether to escape non-ASCII characters (default: true)
     * @param bool $omitEmptyValues Whether to remove keys with missing values instead of adding empty strings (default: false)
     * @param bool $omitIncompleteStrings Whether to remove keys with incomplete string values instead of closing them (default: false)
     */
    public function __construct(
        private readonly string $json,
        private readonly bool $ensureAscii = true,
        private readonly bool $omitEmptyValues = false,
        private readonly bool $omitIncompleteStrings = false,
    ) {}

    /**
     * Repair the JSON string and return the corrected version.
     *
     * This method attempts to fix various common JSON errors including:
     * - Missing quotes around keys and values
     * - Missing commas between elements
     * - Trailing commas
     * - Unclosed brackets, braces, and strings
     * - Single quotes instead of double quotes
     * - Non-standard boolean/null values (True, False, None)
     * - Incomplete escape sequences
     * - Missing colons in key-value pairs
     *
     * @return string The repaired JSON string
     *
     * @throws \Cortex\JsonRepair\Exceptions\JsonRepairException If the repaired JSON is still invalid
     */
    public function repair(): string
    {
        if (json_validate($this->json)) {
            $this->log('JSON is already valid, returning as-is');

            return $this->json;
        }

        $this->log('Starting JSON repair');

        // Extract JSON from markdown code blocks if present
        $json = $this->extractJsonFromMarkdown($this->json);

        if ($json !== $this->json) {
            $this->log('Extracted JSON from markdown code block');
        }

        $jsonWithoutComments = $this->removeComments($json);

        if ($jsonWithoutComments !== $json) {
            $this->log('Removed comments from JSON');
            $json = $jsonWithoutComments;
        }

        // Handle multiple JSON objects
        $json = $this->extractFirstValidJson($json);

        // Reset state
        $this->state = self::STATE_START;
        $this->pos = 0;
        $this->output = '';
        $this->stack = [];
        $this->inString = false;
        $this->stringDelimiter = '';
        $this->stateBeforeString = self::STATE_START;
        $this->currentKeyStart = -1;

        $length = strlen($json);
        $i = 0;

        while ($i < $length) {
            $char = $json[$i];
            $this->pos = $i;

            // Handle escape sequences in strings
            // @phpstan-ignore identical.alwaysFalse (state changes in loop iterations)
            if ($this->state === self::STATE_IN_STRING_ESCAPE) {
                // If we're at the end of the string and in escape state, the escape is incomplete
                // Just drop the incomplete escape (backslash wasn't added to output yet)
                if ($i >= strlen($json)) {
                    $this->state = self::STATE_IN_STRING;
                    break;
                }

                $extraCharsConsumed = $this->handleEscapeSequence($char, $json);
                $this->state = self::STATE_IN_STRING;
                $i += 1 + $extraCharsConsumed;
                continue;
            }

            // Handle characters inside strings
            // @phpstan-ignore identical.alwaysFalse (state changes in loop iterations)
            if ($this->state === self::STATE_IN_STRING) {
                // Check for smart quotes as closing delimiter
                $smartQuoteLength = $this->getSmartQuoteLength($json, $i);

                // Handle double quote inside single-quoted string - must escape it
                // @phpstan-ignore booleanAnd.alwaysFalse, identical.alwaysFalse (delimiter set when entering string state and can be single quote)
                if ($char === '"' && $this->stringDelimiter === "'") {
                    $this->log('Escaping double quote inside single-quoted string');
                    $this->output .= '\\"';
                    $i++;
                    continue;
                }

                // @phpstan-ignore identical.alwaysFalse (delimiter set when entering string state)
                if ($char === $this->stringDelimiter || $smartQuoteLength > 0) {
                    // Check if this quote should be escaped (it's inside the string value)
                    // @phpstan-ignore identical.alwaysFalse (smartQuoteLength can be 0 when char matches delimiter)
                    $isRegularQuote = $smartQuoteLength === 0;
                    // @phpstan-ignore booleanOr.alwaysFalse
                    $isInValue = $this->stateBeforeString === self::STATE_IN_OBJECT_VALUE // @phpstan-ignore identical.alwaysFalse
                        || $this->stateBeforeString === self::STATE_IN_ARRAY; // @phpstan-ignore identical.alwaysFalse

                    // @phpstan-ignore booleanAnd.leftAlwaysFalse, booleanAnd.rightAlwaysFalse, booleanAnd.alwaysFalse (variables can be true at runtime)
                    if ($isRegularQuote && $isInValue && $this->shouldEscapeQuoteInValue($json, $i)) {
                        $this->log('Escaping embedded quote inside string value');
                        $this->output .= '\\"';
                        $i++;
                        continue;
                    }

                    // Always close with double quote, even if opened with single quote
                    $this->output .= '"';
                    $this->inString = false;
                    $this->stringDelimiter = '';
                    $this->state = $this->getNextStateAfterString();

                    // Reset key tracking after successfully completing a string value
                    if ($this->state === self::STATE_EXPECTING_COMMA_OR_END) {
                        $this->currentKeyStart = -1;
                    }

                    // @phpstan-ignore greater.alwaysTrue (smartQuoteLength can be 0 when char matches delimiter)
                    $i += $smartQuoteLength > 0 ? $smartQuoteLength : 1;
                    continue;
                }

                if ($char === '\\') {
                    // Don't output the backslash yet - let handleEscapeSequence decide
                    $this->state = self::STATE_IN_STRING_ESCAPE;
                    $i++;
                    continue;
                }

                // Check if this is a structural character that should close an unclosed string
                // This handles cases like {"key": "value with no closing quote}
                if (($char === '}' || $char === ']') && $this->shouldCloseStringAtStructuralChar($json, $i)) {
                    $this->log('Closing unclosed string at structural character', [
                        'char' => $char,
                    ]);
                    // Close the string and let the structural character be processed
                    $this->output .= '"';
                    $this->inString = false;
                    $this->stringDelimiter = '';
                    $this->state = $this->getNextStateAfterString();

                    // Reset key tracking
                    if ($this->state === self::STATE_EXPECTING_COMMA_OR_END) {
                        $this->currentKeyStart = -1;
                    }

                    // Don't increment i - let the structural char be processed in the next iteration
                    continue;
                }

                $this->output .= $char;
                $i++;
                continue;
            }

            // Skip whitespace
            if (ctype_space($char)) {
                $i++;
                continue;
            }

            $i = match ($this->state) {
                // @phpstan-ignore match.alwaysTrue (first iteration starts at STATE_START, then changes)
                self::STATE_START => $this->handleStart($json, $i),
                self::STATE_IN_OBJECT_KEY => $this->handleObjectKey($json, $i),
                self::STATE_EXPECTING_COLON => $this->handleExpectingColon($json, $i),
                self::STATE_IN_OBJECT_VALUE => $this->handleObjectValue($json, $i),
                self::STATE_IN_ARRAY => $this->handleArrayValue($json, $i),
                self::STATE_EXPECTING_COMMA_OR_END => $this->handleExpectingCommaOrEnd($json, $i),
                self::STATE_IN_NUMBER => $this->handleNumber($json, $i),
                default => $i + 1,
            };
        }

        // Close any unclosed strings
        // @phpstan-ignore if.alwaysFalse (can be true if string wasn't closed in loop)
        if ($this->inString) {
            // Check if we should remove incomplete string values
            // @phpstan-ignore booleanAnd.alwaysFalse, identical.alwaysFalse (stateBeforeString is set when entering string state and can be STATE_IN_OBJECT_VALUE)
            if ($this->omitIncompleteStrings && $this->stateBeforeString === self::STATE_IN_OBJECT_VALUE) {
                $this->log('Removing incomplete string value (omitIncompleteStrings enabled)');
                $this->removeCurrentKey();
                // Update state after removing key
                $this->state = self::STATE_EXPECTING_COMMA_OR_END;
            } else {
                $this->log('Adding missing closing quote for unclosed string');
                $this->output .= '"';

                // Note: If we were in escape state, the incomplete escape backslash
                // was never added to output (we defer adding it to handleEscapeSequence)

                // Update state after closing string
                $this->state = $this->getNextStateAfterString();
            }

            $this->inString = false;
        }

        // Handle incomplete key (key without colon/value)
        // Check if we're expecting a colon (just finished a key) but don't have one
        // @phpstan-ignore identical.alwaysFalse (state set to STATE_EXPECTING_COLON after closing string key)
        if ($this->state === self::STATE_EXPECTING_COLON) {
            // We have a key but no colon/value - add colon and empty value
            if ($this->omitEmptyValues) {
                $this->log('Removing key without value (omitEmptyValues enabled)');
                $this->removeCurrentKey();
            } else {
                $this->log('Adding missing colon and empty value for incomplete key');
                $this->output .= ':""';
            }

            $this->state = self::STATE_EXPECTING_COMMA_OR_END;
            // @phpstan-ignore identical.alwaysFalse (state can be STATE_IN_OBJECT_KEY for unquoted keys)
        } elseif ($this->state === self::STATE_IN_OBJECT_KEY) {
            // We're still in key state - might have an incomplete unquoted key
            // If output ends with a quote, we have a complete key, add colon and empty value
            if (str_ends_with($this->output, '"') && ! str_ends_with($this->output, ':""')) {
                if ($this->omitEmptyValues) {
                    $this->removeCurrentKey();
                } else {
                    $this->output .= ':""';
                }
            }
        }

        // If we're in OBJECT_VALUE state and output ends with ':' (possibly with trailing space), add empty string
        $trimmedForCheck = rtrim($this->output);

        // @phpstan-ignore booleanAnd.alwaysFalse, identical.alwaysFalse (state can change during loop)
        if ($this->state === self::STATE_IN_OBJECT_VALUE && str_ends_with($trimmedForCheck, ':')) {
            $this->output = $trimmedForCheck;

            if ($this->omitEmptyValues) {
                $this->removeCurrentKey();
            } else {
                $this->output .= '""';
            }

            $this->state = self::STATE_EXPECTING_COMMA_OR_END;
        }

        // Close any unclosed brackets/braces
        while ($this->stack !== []) {
            $expected = array_pop($this->stack);
            $this->log('Adding missing closing bracket/brace', [
                'char' => $expected,
            ]);

            // Remove trailing comma before closing
            $this->removeTrailingComma();

            $trimmedForBrace = rtrim($this->output);

            if ($expected === '}' && str_ends_with($trimmedForBrace, ':')) {
                $this->output = $trimmedForBrace;

                if ($this->omitEmptyValues) {
                    $this->removeCurrentKey();
                } else {
                    $this->output .= '""';
                }
            }

            $this->output .= $expected;
        }

        if (! $this->ensureAscii) {
            $decoded = json_decode($this->output, true);

            if ($decoded !== null) {
                $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($encoded !== false) {
                    $this->output = $encoded;
                }
            }
        }

        if ($this->output !== '' && ! json_validate($this->output)) {
            throw JsonRepairException::invalidJsonAfterRepair($this->output);
        }

        return $this->output;
    }

    /**
     * @param int<1, max> $depth
     *
     * @return array<mixed>|object
     */
    public function decode(
        int $depth = 512,
        int $flags = JSON_THROW_ON_ERROR,
    ): array|object {
        $repaired = $this->repair();
        $decoded = json_decode($repaired, true, $depth, $flags);

        return is_array($decoded) ? $decoded : (object) $decoded;
    }
}
