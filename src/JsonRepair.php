<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

class JsonRepair
{
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

    public function __construct(
        protected string $json,
        private readonly bool $ensureAscii = true,
    ) {}

    public function repair(): string
    {
        if (json_validate($this->json)) {
            return $this->json;
        }

        // Extract JSON from markdown code blocks if present
        $json = $this->extractJsonFromMarkdown($this->json);

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

        $length = strlen($json);
        $i = 0;

        while ($i < $length) {
            $char = $json[$i];
            $this->pos = $i;

            // Handle escape sequences in strings
            // @phpstan-ignore identical.alwaysFalse (state changes in loop iterations)
            if ($this->state === self::STATE_IN_STRING_ESCAPE) {
                // If we're at the end of the string and in escape state, the escape is incomplete
                if ($i >= strlen($json)) {
                    // Remove the backslash, treat as literal character
                    $this->output = substr($this->output, 0, -1);
                    $this->state = self::STATE_IN_STRING;
                    break;
                }

                $this->handleEscapeSequence($char);
                $this->state = self::STATE_IN_STRING;
                $i++;
                continue;
            }

            // Handle characters inside strings
            // @phpstan-ignore identical.alwaysFalse (state changes in loop iterations)
            if ($this->state === self::STATE_IN_STRING) {
                // @phpstan-ignore identical.alwaysFalse (delimiter set when entering string state)
                if ($char === $this->stringDelimiter) {
                    // Always close with double quote, even if opened with single quote
                    $this->output .= '"';
                    $this->inString = false;
                    $this->stringDelimiter = '';
                    $this->state = $this->getNextStateAfterString();
                    $i++;
                    continue;
                }

                if ($char === '\\') {
                    $this->output .= $char;
                    $this->state = self::STATE_IN_STRING_ESCAPE;
                    $i++;
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
            $this->output .= '"';

            // If we were in a string escape state, the escape was incomplete
            // @phpstan-ignore identical.alwaysFalse (state can be STATE_IN_STRING_ESCAPE if string ended during escape)
            if ($this->state === self::STATE_IN_STRING_ESCAPE) {
                // Remove the incomplete escape backslash
                $this->output = substr($this->output, 0, -2) . substr($this->output, -1);
            }

            // Update state after closing string
            $this->state = $this->getNextStateAfterString();
            $this->inString = false;
        }

        // Handle incomplete key (key without colon/value)
        // Check if we're expecting a colon (just finished a key) but don't have one
        // @phpstan-ignore identical.alwaysFalse (state set to STATE_EXPECTING_COLON after closing string key)
        if ($this->state === self::STATE_EXPECTING_COLON) {
            // We have a key but no colon/value - add colon and empty value
            $this->output .= ':""';
            $this->state = self::STATE_EXPECTING_COMMA_OR_END;
            // @phpstan-ignore identical.alwaysFalse (state can be STATE_IN_OBJECT_KEY for unquoted keys)
        } elseif ($this->state === self::STATE_IN_OBJECT_KEY) {
            // We're still in key state - might have an incomplete unquoted key
            // If output ends with a quote, we have a complete key, add colon and empty value
            if (str_ends_with($this->output, '"') && ! str_ends_with($this->output, ':"')) {
                $this->output .= ':""';
            }
        }

        // If we're in OBJECT_VALUE state and output ends with ':', add empty string
        // @phpstan-ignore booleanAnd.alwaysFalse, identical.alwaysFalse (state can change during loop)
        if ($this->state === self::STATE_IN_OBJECT_VALUE && str_ends_with($this->output, ':')) {
            $this->output .= '""';
            $this->state = self::STATE_EXPECTING_COMMA_OR_END;
        }

        // Close any unclosed brackets/braces
        while ($this->stack !== []) {
            $expected = array_pop($this->stack);

            // Remove trailing comma before closing
            $this->removeTrailingComma();

            if ($expected === '}' && str_ends_with($this->output, ':')) {
                $this->output .= '""';
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
            $this->output .= '"';
            $this->inString = true;
            $this->stringDelimiter = $char;
            $this->stateBeforeString = self::STATE_IN_OBJECT_KEY;
            $this->state = self::STATE_IN_STRING;

            return $i + 1;
        }

        // Unquoted key
        if (ctype_alnum($char) || $char === '_' || $char === '-') {
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

    private function handleExpectingColon(string $json, int $i): int
    {
        $char = $json[$i];

        if ($char === ':') {
            $this->output .= ':';
            $this->state = self::STATE_IN_OBJECT_VALUE;

            return $i + 1;
        }

        // Missing colon, insert it
        if (! ctype_space($char)) {
            $this->output .= ':';
            $this->state = self::STATE_IN_OBJECT_VALUE;

            return $i;
        }

        return $i + 1;
    }

    private function handleObjectValue(string $json, int $i): int
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

        if ($char === '"' || $char === "'") {
            $this->output .= '"';
            $this->inString = true;
            $this->stringDelimiter = $char;
            $this->stateBeforeString = self::STATE_IN_OBJECT_VALUE;
            $this->state = self::STATE_IN_STRING;

            return $i + 1;
        }

        if ($char === '}') {
            if (str_ends_with($this->output, ':')) {
                $this->output .= '""';
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
            $this->output .= $this->normalizeBoolean($matches[1]);
            $this->state = self::STATE_EXPECTING_COMMA_OR_END;

            return $i + strlen($matches[1]);
        }

        // Handle numbers
        if (ctype_digit($char) || $char === '-' || $char === '+') {
            $this->state = self::STATE_IN_NUMBER;

            return $i;
        }

        // Missing value
        if ($char === ',' || $char === '}') {
            $this->output .= '""';
            $this->state = self::STATE_EXPECTING_COMMA_OR_END;

            return $i;
        }

        return $i + 1;
    }

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

            return $i + 1;
        }

        // Missing comma, insert it
        if (! ctype_space($char) && $char !== $top) {
            $this->output .= ',';
            $this->state = $top === '}' ? self::STATE_IN_OBJECT_KEY : self::STATE_IN_ARRAY;

            return $i;
        }

        return $i + 1;
    }

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

        return $i;
    }

    private function handleEscapeSequence(string $char): void
    {
        $escapeMap = [
            '"' => '"',
            '\\' => '\\',
            '/' => '/',
            'b' => "\b",
            'f' => "\f",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
        ];

        if (isset($escapeMap[$char])) {
            $this->output .= '\\' . $char;
        } elseif ($char === 'u' && $this->pos + 4 < strlen($this->json)) {
            // Unicode escape
            $hex = substr($this->json, $this->pos + 1, 4);

            if (ctype_xdigit($hex)) {
                $this->output .= '\\u' . $hex;
            } else {
                // Invalid unicode escape - output as literal backslash + u
                $this->output .= '\\' . $char;
            }
        } else {
            // Unknown escape sequence or incomplete - output as literal backslash + char
            // This handles incomplete escapes (e.g., string ends with \)
            $this->output .= '\\' . $char;
        }
    }

    private function getNextStateAfterString(): int
    {
        return $this->stateBeforeString === self::STATE_IN_OBJECT_KEY
            ? self::STATE_EXPECTING_COLON
            : self::STATE_EXPECTING_COMMA_OR_END;
    }

    private function removeTrailingComma(): void
    {
        if (str_ends_with($this->output, ',')) {
            $this->output = substr($this->output, 0, -1);
        }
    }

    private function normalizeBoolean(string $value): string
    {
        return match (strtolower($value)) {
            'true' => 'true',
            'false' => 'false',
            default => 'null',
        };
    }
}
