<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Concerns;

/**
 * @mixin \Cortex\JsonRepair\JsonRepairer
 */
trait InputSanitization
{
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
}
