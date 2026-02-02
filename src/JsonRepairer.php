<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Cortex\JsonRepair\Exceptions\JsonRepairException;

class JsonRepairer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
            if (str_ends_with($this->output, '"') && ! str_ends_with($this->output, ':"')) {
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

    /**
     * Extract JSON content from markdown code blocks.
     *
     * Looks for ```json or ``` code blocks and returns the content.
     * If no markdown blocks are found, returns the input as-is.
     *
     * @param string $input The input string that may contain markdown code blocks
     *
     * @return string The extracted JSON content or original input
     */
    private function extractJsonFromMarkdown(string $input): string
    {
        $matchCount = preg_match_all('/```json\s*([\s\S]*?)\s*```/', $input, $matches);

        if ($matchCount > 0) {
            return implode('', $matches[1]);
        }

        $matchCount = preg_match_all('/```\s*([\s\S]*?)\s*```/', $input, $matches);

        if ($matchCount > 0) {
            return implode('', $matches[1]);
        }

        return $input;
    }

    /**
     * Remove single-line (//) and multi-line (slash-star) comments from JSON-like input.
     *
     * This method is careful to ignore comment markers inside string literals.
     *
     * @param string $input The input string that may contain comments
     *
     * @return string The input with comments removed
     */
    private function removeComments(string $input): string
    {
        $length = strlen($input);
        $output = '';
        $inString = false;
        $stringDelimiter = '';
        $escapeNext = false;
        $parityEscapeNext = false;
        $doubleQuoteParity = 0;
        $singleQuoteParity = 0;
        $i = 0;

        while ($i < $length) {
            $char = $input[$i];

            if (! $inString) {
                if ($char === "\n" || $char === "\r") {
                    $doubleQuoteParity = 0;
                    $singleQuoteParity = 0;
                    $parityEscapeNext = false;
                } elseif ($parityEscapeNext) {
                    $parityEscapeNext = false;
                } elseif ($char === '\\') {
                    $parityEscapeNext = true;
                }
            }

            if ($escapeNext) {
                $output .= $char;
                $escapeNext = false;
                $i++;
                continue;
            }

            if ($inString) {
                if ($char === '\\') {
                    $output .= $char;
                    $escapeNext = true;
                    $i++;
                    continue;
                }

                if ($char === $stringDelimiter) {
                    $inString = false;
                    $stringDelimiter = '';

                    if ($char === '"') {
                        $doubleQuoteParity ^= 1;
                    } else {
                        $singleQuoteParity ^= 1;
                    }
                }

                $output .= $char;
                $i++;
                continue;
            }

            if ($char === '"' || $char === "'") {
                if (! $parityEscapeNext) {
                    if ($char === '"') {
                        $doubleQuoteParity ^= 1;
                    } else {
                        $singleQuoteParity ^= 1;
                    }
                }

                $inString = true;
                $stringDelimiter = $char;
                $output .= $char;
                $i++;
                continue;
            }

            if ($char === '/' && $i + 1 < $length) {
                $next = $input[$i + 1];

                if ($next === '/') {
                    if (($doubleQuoteParity === 1) || ($singleQuoteParity === 1)) {
                        $output .= $char;
                        $i++;
                        continue;
                    }

                    if ($this->isSchemePrefixBeforeComment($input, $i)) {
                        $output .= $char;
                        $i++;
                        continue;
                    }

                    $this->log('Removing single-line comment');
                    $i += 2;

                    while ($i < $length && $input[$i] !== "\n" && $input[$i] !== "\r") {
                        $i++;
                    }

                    if ($i < $length && ($input[$i] === "\n" || $input[$i] === "\r")) {
                        $output .= $input[$i];
                        $i++;
                    } else {
                        $this->skipDuplicateSpaceAfterComment($output, $input, $i);
                        $this->appendCommentSeparatorIfNeeded($output, $input, $i);
                    }

                    continue;
                }

                if ($next === '*') {
                    $this->log('Removing multi-line comment');
                    $i += 2;

                    while ($i + 1 < $length && ! ($input[$i] === '*' && $input[$i + 1] === '/')) {
                        $i++;
                    }

                    if ($i + 1 < $length) {
                        $i += 2;
                    } else {
                        $i = $length;
                    }

                    $this->skipDuplicateSpaceAfterComment($output, $input, $i);
                    $this->appendCommentSeparatorIfNeeded($output, $input, $i);
                    continue;
                }
            }

            $output .= $char;
            $i++;
        }

        return $output;
    }

    /**
     * Append a separating space after removing a comment if needed to avoid token merging.
     *
     * @param string $output The output buffer (passed by reference)
     * @param string $input The original input string
     * @param int $nextIndex The next index to be processed in the input
     */
    private function appendCommentSeparatorIfNeeded(string &$output, string $input, int $nextIndex): void
    {
        if ($output === '' || $nextIndex >= strlen($input)) {
            return;
        }

        $lastChar = $output[strlen($output) - 1];
        $nextChar = $input[$nextIndex];

        if (! ctype_space($lastChar) && ! ctype_space($nextChar)) {
            $output .= ' ';
        }
    }

    /**
     * Avoid leaving two spaces in a row when a comment is removed.
     *
     * @param string $output The output buffer (passed by reference)
     * @param string $input The original input string
     * @param int $nextIndex The next index to be processed in the input
     */
    private function skipDuplicateSpaceAfterComment(string $output, string $input, int &$nextIndex): void
    {
        if ($output === '' || $nextIndex >= strlen($input)) {
            return;
        }

        $lastChar = $output[strlen($output) - 1];
        $nextChar = $input[$nextIndex];

        if ($lastChar === ' ' && $nextChar === ' ') {
            $nextIndex++;
        }
    }

    /**
     * Determine if the slashes are part of a URL scheme (e.g. https://).
     *
     * @param string $input The original input string
     * @param int $slashIndex The index of the first slash in the pair
     */
    private function isSchemePrefixBeforeComment(string $input, int $slashIndex): bool
    {
        if ($slashIndex < 2 || $input[$slashIndex - 1] !== ':') {
            return false;
        }

        $j = $slashIndex - 2;

        if (! ctype_alpha($input[$j])) {
            return false;
        }

        while ($j >= 0 && ctype_alpha($input[$j])) {
            $j--;
        }

        $schemeLength = ($slashIndex - 1) - ($j + 1);

        return $schemeLength >= 2;
    }

    /**
     * Extract the first valid JSON object or array from the input.
     *
     * Scans the input to find the longest valid JSON object or array.
     * This is useful when JSON is embedded in other text or when
     * there are multiple JSON structures.
     *
     * @param string $input The input string that may contain JSON
     *
     * @return string The first valid JSON found, or the original input if none found
     */
    private function extractFirstValidJson(string $input): string
    {
        if (json_validate($input)) {
            return $input;
        }

        $length = strlen($input);
        $bestMatch = null;
        $bestLength = 0;
        $depth = 0;
        $start = -1;
        $inString = false;
        $stringDelimiter = '';
        $escapeNext = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];

            if ($escapeNext) {
                $escapeNext = false;
                continue;
            }

            if ($inString) {
                if ($char === '\\') {
                    $escapeNext = true;
                    continue;
                }

                if ($char === $stringDelimiter) {
                    $inString = false;
                    $stringDelimiter = '';
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $stringDelimiter = $char;
                continue;
            }

            if ($char === '{') {
                if ($start === -1) {
                    $start = $i;
                }

                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0 && $start !== -1) {
                    $extracted = substr($input, $start, $i - $start + 1);

                    if (json_validate($extracted)) {
                        $extractedLength = strlen($extracted);

                        if ($bestMatch === null || $extractedLength > $bestLength) {
                            $bestMatch = $extracted;
                            $bestLength = $extractedLength;
                        }
                    }

                    $start = -1;
                }
            } elseif ($char === '[' && $start === -1) {
                $start = $i;
                $depth = 1;
            } elseif ($char === ']' && $start !== -1) {
                $depth--;

                if ($depth === 0) {
                    $extracted = substr($input, $start, $i - $start + 1);

                    if (json_validate($extracted) && $bestMatch === null) {
                        $bestMatch = $extracted;
                        $bestLength = strlen($extracted);
                    }

                    $start = -1;
                }
            }
        }

        return $bestMatch ?? $input;
    }

    /**
     * Handle the starting state of parsing.
     *
     * Processes the first character of the JSON, expecting either an object
     * opening brace or an array opening bracket.
     *
     * @param string $json The JSON string being parsed
     * @param int $i The current position in the string
     *
     * @return int The next position to parse
     */
    private function handleStart(string $json, int $i): int
    {
        $char = $json[$i];

        if ($char === '{') {
            $this->output .= '{';
            $this->stack[] = '}';
            $this->state = self::STATE_IN_OBJECT_KEY;

            return $i + 1;
        }

        if ($char === '[') {
            $this->output .= '[';
            $this->stack[] = ']';
            $this->state = self::STATE_IN_ARRAY;

            return $i + 1;
        }

        // Unexpected character at start
        return $i + 1;
    }

    /**
     * Handle parsing an object key.
     *
     * Processes keys within a JSON object, which can be quoted, single-quoted,
     * or unquoted (containing only alphanumeric characters, underscores, or hyphens).
     *
     * @param string $json The JSON string being parsed
     * @param int $i The current position in the string
     *
     * @return int The next position to parse
     */
    private function handleObjectKey(string $json, int $i): int
    {
        $char = $json[$i];

        if ($char === '}') {
            $this->removeTrailingComma();
            $this->output .= '}';
            array_pop($this->stack);
            $this->state = $this->stack === [] ? self::STATE_START : self::STATE_EXPECTING_COMMA_OR_END;

            return $i + 1;
        }

        if ($char === '"' || $char === "'") {
            // Check for double-quote delimiter pattern like ""key"" (slanted delimiter style)
            // If we have ""X where X is alphanumeric, skip the double quotes and read as unquoted key
            if ($i + 2 < strlen($json) && $json[$i + 1] === $char) {
                $afterDoubleQuote = $json[$i + 2];

                if (ctype_alnum($afterDoubleQuote) || $afterDoubleQuote === '_' || $afterDoubleQuote === ' ') {
                    $this->log('Found doubled quote delimiter pattern, normalizing key');
                    // This looks like ""key"" pattern - skip the opening "" and read the key
                    $this->currentKeyStart = strlen($this->output);
                    $this->output .= '"';
                    $keyStart = $i + 2;
                    $keyEnd = $keyStart;

                    // Read until we hit the closing "" or single " or : or }
                    while ($keyEnd < strlen($json)) {
                        $keyChar = $json[$keyEnd];

                        // Check for closing "" pattern
                        if (($keyChar === '"' || $keyChar === "'") && $keyEnd + 1 < strlen(
                            $json,
                        ) && $json[$keyEnd + 1] === $keyChar) {
                            break;
                        }

                        // Also stop at single quote followed by colon (end of key)
                        if (($keyChar === '"' || $keyChar === "'") && $keyEnd + 1 < strlen(
                            $json,
                        ) && $json[$keyEnd + 1] === ':') {
                            break;
                        }

                        // Stop at colon or closing brace
                        if ($keyChar === ':' || $keyChar === '}') {
                            break;
                        }

                        $this->output .= $keyChar;
                        $keyEnd++;
                    }

                    $this->output .= '"';
                    $this->state = self::STATE_EXPECTING_COLON;

                    // Skip past the closing "" if present
                    if ($keyEnd + 1 < strlen(
                        $json,
                    ) && ($json[$keyEnd] === '"' || $json[$keyEnd] === "'") && $json[$keyEnd + 1] === $json[$keyEnd]) {
                        return $keyEnd + 2;
                    }

                    // Skip past single closing " if present (followed by :)
                    if ($keyEnd < strlen($json) && ($json[$keyEnd] === '"' || $json[$keyEnd] === "'")) {
                        return $keyEnd + 1;
                    }

                    return $keyEnd;
                }
            }

            if ($char === "'") {
                $this->log('Converting single-quoted key to double quotes');
            }

            // Track where the key starts
            $this->currentKeyStart = strlen($this->output);
            $this->output .= '"';
            $this->inString = true;
            $this->stringDelimiter = $char;
            $this->stateBeforeString = self::STATE_IN_OBJECT_KEY;
            $this->state = self::STATE_IN_STRING;

            return $i + 1;
        }

        // Handle smart/curly quotes as key delimiters
        $smartQuoteLength = $this->getSmartQuoteLength($json, $i);

        if ($smartQuoteLength > 0) {
            $this->log('Converting smart/curly quote to standard double quote');
            $this->currentKeyStart = strlen($this->output);
            $this->output .= '"';
            $this->inString = true;
            $this->stringDelimiter = '"'; // Normalize to regular quote
            $this->stateBeforeString = self::STATE_IN_OBJECT_KEY;
            $this->state = self::STATE_IN_STRING;

            return $i + $smartQuoteLength;
        }

        // Unquoted key
        if (ctype_alnum($char) || $char === '_' || $char === '-') {
            $this->log('Adding quotes around unquoted key');
            // Track where the key starts
            $this->currentKeyStart = strlen($this->output);
            $this->output .= '"';
            while ($i < strlen($json) && (ctype_alnum($json[$i]) || $json[$i] === '_' || $json[$i] === '-')) {
                $this->output .= $json[$i];
                $i++;
            }

            $this->output .= '"';
            $this->state = self::STATE_EXPECTING_COLON;

            return $i;
        }

        return $i + 1;
    }

    /**
     * Handle the state expecting a colon after an object key.
     *
     * Processes the colon separator between a key and its value in an object.
     * If a colon is not present, one will be inserted.
     *
     * @param string $json The JSON string being parsed
     * @param int $i The current position in the string
     *
     * @return int The next position to parse
     */
    private function handleExpectingColon(string $json, int $i): int
    {
        $char = $json[$i];

        if ($char === ':') {
            $this->output .= ':';
            $this->state = self::STATE_IN_OBJECT_VALUE;

            // Preserve whitespace after colon
            $nextI = $i + 1;
            while ($nextI < strlen($json) && $json[$nextI] === ' ') {
                $this->output .= ' ';
                $nextI++;
            }

            return $nextI;
        }

        // Missing colon, insert it
        if (! ctype_space($char)) {
            $this->log('Inserting missing colon after key');
            $this->output .= ':';
            $this->state = self::STATE_IN_OBJECT_VALUE;

            return $i;
        }

        return $i + 1;
    }

    /**
     * Handle parsing an object value.
     *
     * Processes the value portion of a key-value pair in an object.
     * Can handle nested objects, arrays, strings, booleans, null, and numbers.
     *
     * @param string $json The JSON string being parsed
     * @param int $i The current position in the string
     *
     * @return int The next position to parse
     */
    private function handleObjectValue(string $json, int $i): int
    {
        $char = $json[$i];

        if ($char === '{') {
            $this->output .= '{';
            $this->stack[] = '}';
            $this->state = self::STATE_IN_OBJECT_KEY;
            // Reset key tracking when starting a nested object (this is a value, not a key)
            $this->currentKeyStart = -1;

            return $i + 1;
        }

        if ($char === '[') {
            $this->output .= '[';
            $this->stack[] = ']';
            $this->state = self::STATE_IN_ARRAY;
            // Reset key tracking when starting a nested array (this is a value, not a key)
            $this->currentKeyStart = -1;

            return $i + 1;
        }

        if ($char === '"' || $char === "'") {
            // Check for double quote at start of value (e.g., {"key": ""value"})
            // Skip the first quote if it's immediately followed by another quote and then non-quote content
            // Check what comes after the second quote
            if ($i + 1 < strlen($json) && $json[$i + 1] === $char && ($i + 2 < strlen(
                $json,
            ) && $json[$i + 2] !== $char && $json[$i + 2] !== '}' && $json[$i + 2] !== ',')) {
                // Pattern like ""value" - skip the empty quotes and use the value
                // Skip the first quote entirely
                return $i + 1;
            }

            $this->output .= '"';
            $this->inString = true;
            $this->stringDelimiter = $char;
            $this->stateBeforeString = self::STATE_IN_OBJECT_VALUE;
            $this->state = self::STATE_IN_STRING;

            return $i + 1;
        }

        if ($char === '}') {
            // Check for missing value - output ends with colon (possibly followed by space)
            $trimmedOutput = rtrim($this->output);

            if (str_ends_with($trimmedOutput, ':')) {
                // Remove trailing space(s) after colon before adding empty value
                $this->output = $trimmedOutput;

                if ($this->omitEmptyValues) {
                    $this->log('Removing key with missing value (omitEmptyValues enabled)');
                    $this->removeCurrentKey();
                } else {
                    $this->log('Adding empty string for missing value');
                    $this->output .= '""';
                }
            }

            $this->removeTrailingComma();
            $this->output .= '}';
            array_pop($this->stack);
            $this->state = $this->stack === [] ? self::STATE_START : self::STATE_EXPECTING_COMMA_OR_END;

            return $i + 1;
        }

        // Handle non-standard booleans/null (True/False/None)
        $matchResult = preg_match('/^(true|false|null|True|False|None)\b/i', substr($json, $i), $matches);

        if ($matchResult === 1) {
            $normalized = $this->normalizeBoolean($matches[1]);

            if ($matches[1] !== $normalized) {
                $this->log('Normalizing boolean/null value', [
                    'from' => $matches[1],
                    'to' => $normalized,
                ]);
            }

            $this->output .= $normalized;
            $this->state = self::STATE_EXPECTING_COMMA_OR_END;
            // Reset key tracking after successfully completing a boolean/null value
            $this->currentKeyStart = -1;

            return $i + strlen($matches[1]);
        }

        // Handle numbers
        if (ctype_digit($char) || $char === '-' || $char === '+') {
            $this->state = self::STATE_IN_NUMBER;

            return $i;
        }

        // Missing value
        if ($char === ',' || $char === '}') {
            if ($this->omitEmptyValues) {
                $this->log('Removing key with missing value (omitEmptyValues enabled)');
                $this->removeCurrentKey();
            } else {
                $this->log('Adding empty string for missing value');
                $this->output .= '""';
            }

            $this->state = self::STATE_EXPECTING_COMMA_OR_END;

            return $i;
        }

        // Handle smart/curly quotes - treat them as regular quotes
        $smartQuoteLength = $this->getSmartQuoteLength($json, $i);

        if ($smartQuoteLength > 0) {
            $this->output .= '"';
            $this->inString = true;
            $this->stringDelimiter = '"';
            $this->stateBeforeString = self::STATE_IN_OBJECT_VALUE;
            $this->state = self::STATE_IN_STRING;

            return $i + $smartQuoteLength;
        }

        // Handle unquoted string values
        if (ctype_alpha($char) || $char === '_') {
            $this->log('Found unquoted string value, adding quotes');

            return $this->handleUnquotedStringValue($json, $i);
        }

        return $i + 1;
    }

    /**
     * Handle parsing an array value.
     *
     * Processes elements within a JSON array.
     * Can handle nested objects, arrays, strings, booleans, null, and numbers.
     *
     * @param string $json The JSON string being parsed
     * @param int $i The current position in the string
     *
     * @return int The next position to parse
     */
    private function handleArrayValue(string $json, int $i): int
    {
        $char = $json[$i];

        if ($char === ']') {
            $this->removeTrailingComma();
            $this->output .= ']';
            array_pop($this->stack);
            $this->state = $this->stack === [] ? self::STATE_START : self::STATE_EXPECTING_COMMA_OR_END;

            return $i + 1;
        }

        if ($char === '{') {
            $this->output .= '{';
            $this->stack[] = '}';
            $this->state = self::STATE_IN_OBJECT_KEY;

            return $i + 1;
        }

        if ($char === '[') {
            $this->output .= '[';
            $this->stack[] = ']';
            $this->state = self::STATE_IN_ARRAY;

            return $i + 1;
        }

        if ($char === '"' || $char === "'") {
            $this->output .= '"';
            $this->inString = true;
            $this->stringDelimiter = $char;
            $this->stateBeforeString = self::STATE_IN_ARRAY;
            $this->state = self::STATE_IN_STRING;

            return $i + 1;
        }

        // Handle non-standard booleans/null (True/False/None)
        $matchResult = preg_match('/^(true|false|null|True|False|None)\b/i', substr($json, $i), $matches);

        if ($matchResult === 1) {
            $this->output .= $this->normalizeBoolean($matches[1]);
            $this->state = self::STATE_EXPECTING_COMMA_OR_END;

            return $i + strlen($matches[1]);
        }

        // Handle numbers
        if (ctype_digit($char) || $char === '-' || $char === '+') {
            $this->state = self::STATE_IN_NUMBER;

            return $i;
        }

        return $i + 1;
    }

    /**
     * Handle the state expecting a comma or closing bracket/brace.
     *
     * Processes the separator between elements in an array or key-value pairs
     * in an object, or the closing character that ends the structure.
     *
     * @param string $json The JSON string being parsed
     * @param int $i The current position in the string
     *
     * @return int The next position to parse
     */
    private function handleExpectingCommaOrEnd(string $json, int $i): int
    {
        $char = $json[$i];
        $top = end($this->stack);

        if ($top !== false && $char === $top) {
            $this->removeTrailingComma();
            $this->output .= $top;
            array_pop($this->stack);
            $this->state = $this->stack === [] ? self::STATE_START : self::STATE_EXPECTING_COMMA_OR_END;

            return $i + 1;
        }

        if ($char === ',') {
            $this->output .= ',';
            $this->state = $top === '}' ? self::STATE_IN_OBJECT_KEY : self::STATE_IN_ARRAY;

            // Preserve whitespace after comma
            $nextI = $i + 1;
            while ($nextI < strlen($json) && $json[$nextI] === ' ') {
                $this->output .= ' ';
                $nextI++;
            }

            return $nextI;
        }

        // Missing comma, insert it
        if (! ctype_space($char) && $char !== $top) {
            $this->log('Inserting missing comma');
            $this->output .= ',';
            $this->state = $top === '}' ? self::STATE_IN_OBJECT_KEY : self::STATE_IN_ARRAY;

            return $i;
        }

        return $i + 1;
    }

    /**
     * Handle parsing a numeric value.
     *
     * Processes numbers including integers, floats, and numbers with
     * scientific notation (e.g., 1.23e-4). Handles positive and negative
     * signs, decimal points, and exponents.
     *
     * @param string $json The JSON string being parsed
     * @param int $i The current position in the string
     *
     * @return int The next position to parse
     */
    private function handleNumber(string $json, int $i): int
    {
        $length = strlen($json);

        // Handle sign
        if ($i < $length && ($json[$i] === '-' || $json[$i] === '+')) {
            $this->output .= $json[$i];
            $i++;
        }

        // Handle integer part
        while ($i < $length && ctype_digit($json[$i])) {
            $this->output .= $json[$i];
            $i++;
        }

        // Handle decimal point
        if ($i < $length && $json[$i] === '.') {
            $this->output .= '.';
            $i++;
            while ($i < $length && ctype_digit($json[$i])) {
                $this->output .= $json[$i];
                $i++;
            }
        }

        // Handle exponent
        if ($i < $length && ($json[$i] === 'e' || $json[$i] === 'E')) {
            $exponentStart = $i;
            $this->output .= $json[$i];
            $i++;

            if ($i < $length && ($json[$i] === '-' || $json[$i] === '+')) {
                $this->output .= $json[$i];
                $i++;
            }

            $hasExponentDigits = false;
            while ($i < $length && ctype_digit($json[$i])) {
                $this->output .= $json[$i];
                $i++;
                $hasExponentDigits = true;
            }

            // If we started an exponent but don't have digits, remove the incomplete exponent
            if (! $hasExponentDigits) {
                // Remove the 'e' or 'E' and optional sign
                $exponentLength = $i - $exponentStart;
                $this->output = substr($this->output, 0, -$exponentLength);
            }
        }

        $this->state = self::STATE_EXPECTING_COMMA_OR_END;
        // Reset key tracking after successfully completing a number value
        $this->currentKeyStart = -1;

        return $i;
    }

    /**
     * Handle an escape sequence within a string.
     *
     * Processes escape sequences like \", \\, \/, \b, \f, \n, \r, \t, and
     * unicode escapes (\uXXXX). Invalid or incomplete escapes are treated
     * as literal backslash followed by the character.
     */
    /**
     * Handle an escape sequence within a string.
     *
     * Processes escape sequences like \", \\, \/, \b, \f, \n, \r, \t, and
     * unicode escapes (\uXXXX). Invalid or incomplete escapes are treated
     * as escaped backslash followed by the character.
     *
     * @return int Number of extra characters consumed beyond the escape character itself
     */
    private function handleEscapeSequence(string $char, string $json): int
    {
        $validEscapes = ['"', '\\', '/', 'b', 'f', 'n', 'r', 't'];

        if (in_array($char, $validEscapes, true)) {
            $this->output .= '\\' . $char;

            return 0;
        }

        if ($char === 'u' && $this->pos + 4 < strlen($json)) {
            $hex = substr($json, $this->pos + 1, 4);

            if (ctype_xdigit($hex)) {
                $this->output .= '\\u' . $hex;

                return 4; // Consumed 4 extra hex digits
            }
        }

        // Invalid escape sequence - escape the backslash and output the character literally
        $this->output .= '\\\\' . $char;

        return 0;
    }

    /**
     * Determine the next state after completing a string.
     *
     * Returns STATE_EXPECTING_COLON after a key, or STATE_EXPECTING_COMMA_OR_END after a value.
     */
    private function getNextStateAfterString(): int
    {
        if ($this->stateBeforeString === self::STATE_IN_OBJECT_KEY) {
            return self::STATE_EXPECTING_COLON;
        }

        return self::STATE_EXPECTING_COMMA_OR_END;
    }

    /**
     * Remove a trailing comma from the output.
     */
    private function removeTrailingComma(): void
    {
        $trimmed = rtrim($this->output);

        if (str_ends_with($trimmed, ',')) {
            $this->log('Removing trailing comma');
            $this->output = substr($trimmed, 0, -1);
        }
    }

    /**
     * Normalize boolean/null values to proper JSON format.
     *
     * Converts non-standard boolean/null values (True, False, None) to
     * their proper JSON equivalents (true, false, null).
     *
     * @param string $value The value to normalize
     *
     * @return string The normalized JSON value (true, false, or null)
     */
    private function normalizeBoolean(string $value): string
    {
        return match (strtolower($value)) {
            'true' => 'true',
            'false' => 'false',
            default => 'null',
        };
    }

    /**
     * Remove the current key from the output.
     *
     * Removes the most recently added key and any preceding comma and whitespace.
     * Used when omitEmptyValues or omitIncompleteStrings options are enabled.
     */
    private function removeCurrentKey(): void
    {
        if ($this->currentKeyStart < 0) {
            return;
        }

        $beforeKey = rtrim(substr($this->output, 0, $this->currentKeyStart));

        if (str_ends_with($beforeKey, ',')) {
            $beforeKey = rtrim(substr($beforeKey, 0, -1));
        }

        $this->output = $beforeKey;
        $this->currentKeyStart = -1;
    }

    /**
     * Determine if a string should be closed at a structural character.
     *
     * This method handles cases where a string is missing its closing quote.
     * If no closing quote is found after the current position, the string
     * will be closed at this structural character (} or ]).
     *
     * @param string $json The JSON string being parsed
     * @param int $pos The position of the structural character
     *
     * @return bool True if the string should be closed, false otherwise
     */
    private function shouldCloseStringAtStructuralChar(string $json, int $pos): bool
    {
        $length = strlen($json);
        $char = $json[$pos];

        // Check if there's a closing quote before the end of input
        // If not, this structural character should close the string
        $hasClosingQuote = false;

        for ($i = $pos + 1; $i < $length; $i++) {
            if ($json[$i] === $this->stringDelimiter) {
                $hasClosingQuote = true;
                break;
            }

            // If we hit another structural character of the same type, stop looking
            if ($json[$i] === $char) {
                break;
            }
        }

        // Close string here if no closing quote found after this position
        return ! $hasClosingQuote;
    }

    /**
     * Determine if a quote character inside a string value should be escaped.
     *
     * This method handles cases like {"key": "v"alu"e"} where quotes appear
     * inside the value. It determines whether a quote should be treated as
     * the string terminator or as an embedded quote that needs to be escaped.
     *
     * @param string $json The JSON string being parsed
     * @param int $quotePos The position of the quote character
     *
     * @return bool True if the quote should be escaped, false if it's the string terminator
     */
    private function shouldEscapeQuoteInValue(string $json, int $quotePos): bool
    {
        // Only apply quote escaping logic for object values, not arrays
        // In arrays, quotes typically delimit separate values
        if ($this->stateBeforeString === self::STATE_IN_ARRAY) {
            return false;
        }

        $length = strlen($json);

        // Look ahead past the quote
        $pos = $quotePos + 1;

        // Skip whitespace
        while ($pos < $length && ctype_space($json[$pos])) {
            $pos++;
        }

        if ($pos >= $length) {
            // End of string - this quote should close the string
            return false;
        }

        $nextChar = $json[$pos];

        // If next non-whitespace is a structural character, this is a valid closing quote
        if (in_array($nextChar, [',', '}', ']'], true)) {
            return false;
        }

        // If next non-whitespace is a colon, this is starting a new key pattern - don't escape
        if ($nextChar === ':') {
            return false;
        }

        // If the next character is alphabetic or punctuation that could be part of text content,
        // this quote might be embedded. Check if it looks like continuation of a value.
        if (ctype_alpha($nextChar) || $nextChar === '_' || $nextChar === '.') {
            // Look further to see if we find a colon (indicating this starts a new key)
            // or if the pattern looks like continuation of a value
            return $this->looksLikeContinuationNotKey($json, $pos);
        }

        // If next is a quote, check what pattern it forms
        if ($nextChar === '"' || $nextChar === "'") {
            // Could be start of a new key like ", "key2"
            // Look for the key-colon pattern
            return $this->looksLikeEmbeddedQuote($json, $pos);
        }

        return false;
    }

    /**
     * Check if the text starting at $pos looks like string continuation rather than a new key.
     *
     * Scans ahead to determine whether the text after a quote represents
     * continuation of the current value or the start of a new key-value pair.
     */
    private function looksLikeContinuationNotKey(string $json, int $pos): bool
    {
        $length = strlen($json);
        $scanPos = $pos;
        $colonPos = -1;

        while ($scanPos < $length) {
            $char = $json[$scanPos];

            if ($char === ':') {
                $colonPos = $scanPos;
                break;
            }

            if ($char === '"' || $char === "'") {
                return ! $this->isNewKeyValuePair($json, $scanPos);
            }

            if (in_array($char, [',', '}', ']'], true)) {
                return true;
            }

            $scanPos++;
        }

        if ($colonPos === -1) {
            return true;
        }

        $textBeforeColon = trim(substr($json, $pos, $colonPos - $pos));

        // Empty text, spaces, or special characters indicate continuation, not a new key
        if ($textBeforeColon === '' || str_contains($textBeforeColon, ' ')) {
            return true;
        }

        return (bool) preg_match('/[^a-zA-Z0-9_-]/', $textBeforeColon);
    }

    /**
     * Check if a quote at position starts a new key-value pair pattern.
     *
     * Returns true if the quote represents the start of a new key in a "key": "value" pattern.
     */
    private function isNewKeyValuePair(string $json, int $quotePos): bool
    {
        $length = strlen($json);
        $pos = $quotePos + 1;

        // Find the closing quote
        while ($pos < $length && $json[$pos] !== '"' && $json[$pos] !== "'") {
            if ($json[$pos] === '\\' && $pos + 1 < $length) {
                $pos += 2;
                continue;
            }

            $pos++;
        }

        if ($pos >= $length) {
            return false;
        }

        // Skip past closing quote and whitespace
        $pos++;
        while ($pos < $length && ctype_space($json[$pos])) {
            $pos++;
        }

        // A colon following indicates a new key-value pair
        return $pos < $length && $json[$pos] === ':';
    }

    /**
     * Check if a quote at position looks like an embedded quote in a value.
     *
     * Returns true if the quote is embedded within a string value rather than
     * starting a new key-value pair.
     */
    private function looksLikeEmbeddedQuote(string $json, int $quotePos): bool
    {
        return ! $this->isNewKeyValuePair($json, $quotePos);
    }

    /**
     * Handle an unquoted string value in an object.
     *
     * Reads an unquoted string value (e.g., {key: value}) and wraps it in quotes.
     * The value ends when a structural character (, } ]) or a quote is encountered.
     *
     * @param string $json The JSON string being parsed
     * @param int $i The current position in the string
     *
     * @return int The next position to parse
     */
    private function handleUnquotedStringValue(string $json, int $i): int
    {
        $length = strlen($json);
        $value = '';

        // Collect the unquoted value
        while ($i < $length) {
            $char = $json[$i];

            // Stop at structural characters or quotes
            if (in_array($char, [',', '}', ']', '"', "'"], true)) {
                break;
            }

            $value .= $char;
            $i++;
        }

        // Trim trailing whitespace from the value
        $value = rtrim($value);

        // Check if this looks like an incomplete boolean/null (e.g., "tru", "fals", "nul", "tr")
        // These should be treated as empty values, not quoted strings
        $lowerValue = strtolower($value);
        $incompletePatterns = ['t', 'tr', 'tru', 'f', 'fa', 'fal', 'fals', 'n', 'nu', 'nul'];

        if (in_array($lowerValue, $incompletePatterns, true)) {
            // This is an incomplete boolean/null at end of input - treat as empty value
            // Only do this if we're at the end of the JSON (no more meaningful content)
            $remainingJson = substr($json, $i);
            $trimmedRemaining = trim($remainingJson);

            // If the remaining content is just closing braces/brackets, this is incomplete
            if ($trimmedRemaining === '' || preg_match('/^[}\]]+$/', $trimmedRemaining) === 1) {
                if ($this->omitEmptyValues) {
                    $this->removeCurrentKey();
                } else {
                    $this->output .= '""';
                }

                $this->state = self::STATE_EXPECTING_COMMA_OR_END;
                $this->currentKeyStart = -1;

                return $i;
            }
        }

        // If we stopped because we hit a quote, check if it's part of a new key-value pair
        // Check if this looks like a new key pattern ("key":)
        if ($i < $length && ($json[$i] === '"' || $json[$i] === "'") && $this->isNewKeyValuePair($json, $i)) {
            // This is a new key, so the unquoted value ends here
            // Output the unquoted value as a quoted string
            $this->output .= '"' . $this->escapeStringValue($value) . '"';
            $this->currentKeyStart = -1;
            // Insert a comma before the new key and set state to expect the key
            $this->output .= ', ';
            $this->state = self::STATE_IN_OBJECT_KEY;

            return $i;
        }

        // Output the unquoted value as a quoted string
        if ($value !== '') {
            $this->output .= '"' . $this->escapeStringValue($value) . '"';
            $this->state = self::STATE_EXPECTING_COMMA_OR_END;
            $this->currentKeyStart = -1;
        }

        return $i;
    }

    /**
     * Escape special characters in a string value for JSON output.
     */
    private function escapeStringValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    /**
     * Check if the character at the given position is a smart/curly quote.
     *
     * Smart quotes are typographic quote characters like " " ' ' that are
     * sometimes used instead of regular ASCII quotes. Returns the byte length
     * (3 for UTF-8 smart quotes) or 0 if not a smart quote.
     */
    private function getSmartQuoteLength(string $json, int $pos): int
    {
        if ($pos + 2 >= strlen($json)) {
            return 0;
        }

        $threeBytes = substr($json, $pos, 3);

        if (in_array($threeBytes, ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99"], true)) {
            return 3;
        }

        return 0;
    }

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
