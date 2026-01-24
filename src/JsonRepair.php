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

            if ($this->state === self::STATE_IN_STRING_ESCAPE) {
                $this->handleEscapeSequence($char);
                $this->state = self::STATE_IN_STRING;
                $i++;
                continue;
            }

            if ($this->state === self::STATE_IN_STRING) {
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

            switch ($this->state) {
                case self::STATE_START:
                    $i = $this->handleStart($json, $i);
                    break;

                case self::STATE_IN_OBJECT_KEY:
                    $i = $this->handleObjectKey($json, $i);
                    break;

                case self::STATE_EXPECTING_COLON:
                    $i = $this->handleExpectingColon($json, $i);
                    break;

                case self::STATE_IN_OBJECT_VALUE:
                    $i = $this->handleObjectValue($json, $i);
                    break;

                case self::STATE_IN_ARRAY:
                    $i = $this->handleArrayValue($json, $i);
                    break;

                case self::STATE_EXPECTING_COMMA_OR_END:
                    $i = $this->handleExpectingCommaOrEnd($json, $i);
                    break;

                case self::STATE_IN_NUMBER:
                    $i = $this->handleNumber($json, $i);
                    break;

                default:
                    $i++;
            }
        }

        // Close any unclosed strings
        if ($this->inString) {
            $this->output .= '"';
        }

        // If we're in OBJECT_VALUE state and output ends with ':', add empty string
        if ($this->state === self::STATE_IN_OBJECT_VALUE && str_ends_with($this->output, ':')) {
            $this->output .= '""';
            $this->state = self::STATE_EXPECTING_COMMA_OR_END;
        }

        // Close any unclosed brackets/braces
        while ($this->stack !== []) {
            $expected = array_pop($this->stack);

            if ($expected === '}' && str_ends_with($this->output, ':')) {
                $this->output .= '""';
            }

            $this->output .= $expected === '}' ? '}' : ']';
        }

        if (! $this->ensureAscii) {
            $decoded = json_decode($this->output, true);

            if ($decoded !== null) {
                $this->output = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $this->output;
    }

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
        if (preg_match_all('/```json\s*([\s\S]*?)\s*```/', $input, $matches)) {
            return implode('', $matches[1]);
        }

        if (preg_match_all('/```\s*([\s\S]*?)\s*```/', $input, $matches)) {
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
        if (preg_match('/^(true|false|null|True|False|None)\b/i', substr($json, $i), $matches)) {
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
        if (preg_match('/^(true|false|null|True|False|None)\b/i', substr($json, $i), $matches)) {
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

        if ($char === $top) {
            $this->removeTrailingComma();
            $this->output .= $top === '}' ? '}' : ']';
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
        if ($json[$i] === '-' || $json[$i] === '+') {
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
            $this->output .= $json[$i];
            $i++;

            if ($i < $length && ($json[$i] === '-' || $json[$i] === '+')) {
                $this->output .= $json[$i];
                $i++;
            }

            while ($i < $length && ctype_digit($json[$i])) {
                $this->output .= $json[$i];
                $i++;
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
                $this->output .= '\\' . $char;
            }
        } else {
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
