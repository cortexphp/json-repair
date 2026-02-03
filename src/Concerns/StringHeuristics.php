<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Concerns;

/**
 * @mixin \Cortex\JsonRepair\JsonRepairer
 */
trait StringHeuristics
{
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
}
