<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Concerns;

/**
 * @mixin \Cortex\JsonRepair\JsonRepairer
 */
trait StateMachine
{
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
}
