<?php

declare(strict_types=1);

namespace Cortex\JsonRepair;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Cortex\JsonRepair\Concerns\StateMachine;
use Cortex\JsonRepair\Concerns\RepairLogging;
use Cortex\JsonRepair\Concerns\OutputTracking;
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
    use OutputTracking;

    private int $state = ParserState::STATE_START;

    private int $pos = 0;

    private string $output = '';

    /**
     * @var array<string>
     */
    private array $stack = [];

    private bool $inString = false;

    private string $stringDelimiter = '';

    private int $stateBeforeString = ParserState::STATE_START;

    private int $currentKeyStart = -1;

    /**
     * @var list<array<string, int>>
     */
    private array $objectKeysStack = [];

    private bool $skipNextValue = false;

    /**
     * @param string $json The JSON string to repair
     * @param bool $ensureAscii Whether to escape non-ASCII characters (default: true)
     * @param bool $omitEmptyValues Whether to remove keys with missing values instead of adding empty strings (default: false)
     * @param bool $omitIncompleteStrings Whether to remove keys with incomplete string values instead of closing them (default: false)
     * @param DuplicateKeyPolicy|null $duplicateKeyPolicy How to handle duplicate object keys (default: null — no deduplication)
     */
    public function __construct(
        private readonly string $json,
        private readonly bool $ensureAscii = true,
        private readonly bool $omitEmptyValues = false,
        private readonly bool $omitIncompleteStrings = false,
        private readonly ?DuplicateKeyPolicy $duplicateKeyPolicy = null,
    ) {}

    public function repair(): string
    {
        if (json_validate($this->json)) {
            $this->log('JSON is already valid, returning as-is');

            return $this->json;
        }

        $this->log('Starting JSON repair');

        return $this->repairInternal(extractFirstOnly: true);
    }

    public function repairWithDetails(): RepairResult
    {
        $this->beginFixCollection();

        if (json_validate($this->json)) {
            $this->log('JSON is already valid, returning as-is');

            return new RepairResult($this->json, true, $this->endFixCollection());
        }

        $this->log('Starting JSON repair');
        $repaired = $this->repairInternal(extractFirstOnly: true);

        return new RepairResult($repaired, false, $this->endFixCollection());
    }

    /**
     * Repair each top-level JSON value in the input (NDJSON / concatenated objects).
     *
     * @return list<string>
     */
    public function repairAll(): array
    {
        if (json_validate($this->json)) {
            return [$this->json];
        }

        $json = $this->extractJsonFromMarkdown($this->json);
        $json = $this->removeComments($json);

        $segments = $this->extractAllTopLevelJson($json);

        return array_map(
            $this->repairSegment(...),
            $segments,
        );
    }

    /**
     * @param int<1, max> $depth
     */
    public function decode(
        int $depth = 512,
        int $flags = JSON_THROW_ON_ERROR,
    ): mixed {
        $repaired = $this->repair();

        return json_decode($repaired, true, $depth, $flags);
    }

    private function repairSegment(string $json): string
    {
        $repairer = new self(
            $json,
            $this->ensureAscii,
            $this->omitEmptyValues,
            $this->omitIncompleteStrings,
            $this->duplicateKeyPolicy,
        );

        if ($this->logger instanceof LoggerInterface) {
            $repairer->setLogger($this->logger);
        }

        return $repairer->repair();
    }

    private function repairInternal(bool $extractFirstOnly): string
    {
        $json = $this->extractJsonFromMarkdown($this->json);

        if ($json !== $this->json) {
            $this->log('Extracted JSON from markdown code block');
        }

        $jsonWithoutComments = $this->removeComments($json);

        if ($jsonWithoutComments !== $json) {
            $this->log('Removed comments from JSON');
            $json = $jsonWithoutComments;
        }

        if ($extractFirstOnly) {
            $json = $this->extractFirstValidJson($json);
        }

        $this->state = ParserState::STATE_START;
        $this->pos = 0;
        $this->output = '';
        $this->stack = [];
        $this->inString = false;
        $this->stringDelimiter = '';
        $this->stateBeforeString = ParserState::STATE_START;
        $this->currentKeyStart = -1;
        $this->objectKeysStack = [];
        $this->skipNextValue = false;
        $this->resetOutputTracking();

        $length = strlen($json);
        $i = 0;

        while ($i < $length) {
            $char = $json[$i];
            $this->pos = $i;

            if ($this->state === ParserState::STATE_IN_STRING_ESCAPE) {
                $extraCharsConsumed = $this->handleEscapeSequence($char, $json);
                $this->state = ParserState::STATE_IN_STRING;
                $i += 1 + $extraCharsConsumed;
                continue;
            }

            if ($this->state === ParserState::STATE_IN_STRING) {
                $smartQuoteLength = $char === "\xE2" ? $this->getSmartQuoteLength($json, $i) : 0;

                if ($char === '"' && $this->stringDelimiter === "'") {
                    $this->log('Escaping double quote inside single-quoted string');
                    $this->output .= '\\"';
                    $i++;
                    continue;
                }

                if ($char === $this->stringDelimiter || $smartQuoteLength > 0) {
                    $isRegularQuote = $smartQuoteLength === 0;
                    $isInValue = $this->stateBeforeString === ParserState::STATE_IN_OBJECT_VALUE
                        || $this->stateBeforeString === ParserState::STATE_IN_ARRAY;

                    if ($isRegularQuote && $isInValue && $this->shouldEscapeQuoteInValue($json, $i)) {
                        $this->log('Escaping embedded quote inside string value');
                        $this->output .= '\\"';
                        $i++;
                        continue;
                    }

                    $this->output .= '"';
                    $this->inString = false;
                    $this->stringDelimiter = '';
                    $this->state = $this->getNextStateAfterString();

                    if ($this->state === ParserState::STATE_EXPECTING_COMMA_OR_END) {
                        $this->currentKeyStart = -1;
                    }

                    $i += $smartQuoteLength > 0 ? $smartQuoteLength : 1;
                    continue;
                }

                if ($char === '\\') {
                    $this->state = ParserState::STATE_IN_STRING_ESCAPE;
                    $i++;
                    continue;
                }

                if (($char === '}' || $char === ']') && $this->shouldCloseStringAtStructuralChar($json, $i)) {
                    $this->log('Closing unclosed string at structural character', [
                        'char' => $char,
                    ]);
                    $this->output .= '"';
                    $this->inString = false;
                    $this->stringDelimiter = '';
                    $this->state = $this->getNextStateAfterString();

                    if ($this->state === ParserState::STATE_EXPECTING_COMMA_OR_END) {
                        $this->currentKeyStart = -1;
                    }

                    continue;
                }

                $stopChars = '\\' . $this->stringDelimiter . "\"}\xE2";
                $runLength = strcspn($json, $stopChars, $i);

                if ($runLength > 0) {
                    $this->output .= substr($json, $i, $runLength);
                    $i += $runLength;
                    continue;
                }

                $this->output .= $char;
                $i++;
                continue;
            }

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if ($this->skipNextValue && ($this->state === ParserState::STATE_IN_OBJECT_VALUE || $this->state === ParserState::STATE_IN_NUMBER)) {
                $i = $this->skipValueAt($json, $i);
                $this->skipNextValue = false;
                $this->state = ParserState::STATE_EXPECTING_COMMA_OR_END;
                continue;
            }

            $i = match ($this->state) {
                ParserState::STATE_START => $this->handleStart($json, $i),
                ParserState::STATE_IN_OBJECT_KEY => $this->handleObjectKey($json, $i),
                ParserState::STATE_EXPECTING_COLON => $this->handleExpectingColon($json, $i),
                ParserState::STATE_IN_OBJECT_VALUE => $this->handleObjectValue($json, $i),
                ParserState::STATE_IN_ARRAY => $this->handleArrayValue($json, $i),
                ParserState::STATE_EXPECTING_COMMA_OR_END => $this->handleExpectingCommaOrEnd($json, $i),
                ParserState::STATE_IN_NUMBER => $this->handleNumber($json, $i),
                default => $i + 1,
            };
        }

        if ($this->inString) {
            if ($this->omitIncompleteStrings && $this->stateBeforeString === ParserState::STATE_IN_OBJECT_VALUE) {
                $this->log('Removing incomplete string value (omitIncompleteStrings enabled)');
                $this->removeCurrentKey();
                $this->state = ParserState::STATE_EXPECTING_COMMA_OR_END;
            } else {
                $this->log('Adding missing closing quote for unclosed string');
                $this->output .= '"';
                $this->state = $this->getNextStateAfterString();
            }

            $this->inString = false;
        }

        if ($this->state === ParserState::STATE_EXPECTING_COLON) {
            if ($this->omitEmptyValues) {
                $this->log('Removing key without value (omitEmptyValues enabled)');
                $this->removeCurrentKey();
            } else {
                $this->log('Adding missing colon and empty value for incomplete key');
                $this->output .= ':""';
            }

            $this->state = ParserState::STATE_EXPECTING_COMMA_OR_END;
        } elseif ($this->state === ParserState::STATE_IN_OBJECT_KEY) {
            if (str_ends_with($this->output, '"') && ! str_ends_with($this->output, ':""')) {
                if ($this->omitEmptyValues) {
                    $this->removeCurrentKey();
                } else {
                    $this->output .= ':""';
                }
            }
        }

        if ($this->state === ParserState::STATE_IN_OBJECT_VALUE && $this->outputEndsWithNonWhitespace(':')) {
            $this->trimOutputTrailingWhitespace();

            if ($this->omitEmptyValues) {
                $this->removeCurrentKey();
            } else {
                $this->output .= '""';
            }

            $this->state = ParserState::STATE_EXPECTING_COMMA_OR_END;
        }

        while ($this->stack !== []) {
            $expected = array_pop($this->stack);
            $this->log('Adding missing closing bracket/brace', [
                'char' => $expected,
            ]);

            $this->removeTrailingComma();

            if ($expected === '}' && $this->outputEndsWithNonWhitespace(':')) {
                $this->trimOutputTrailingWhitespace();

                if ($this->omitEmptyValues) {
                    $this->removeCurrentKey();
                } else {
                    $this->output .= '""';
                }
            }

            $this->output .= $expected;

            if ($expected === '}') {
                array_pop($this->objectKeysStack);
            }
        }

        return $this->finalizeOutput();
    }

    private function finalizeOutput(): string
    {
        $encodedViaRoundTrip = false;

        if ($this->ensureAscii && preg_match('/[^\x00-\x7F]/', $this->output) === 1) {
            $decoded = json_decode($this->output, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $encoded = json_encode($decoded, JSON_UNESCAPED_SLASHES);

                if ($encoded !== false) {
                    $this->output = $encoded;
                    $encodedViaRoundTrip = true;
                }
            }
        } elseif (! $this->ensureAscii && $this->output !== '') {
            $decoded = json_decode($this->output, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($encoded !== false) {
                    $this->output = $encoded;
                    $encodedViaRoundTrip = true;
                }
            }
        }

        if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::KeepLast && $this->output !== '') {
            $decoded = json_decode($this->output, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $flags = JSON_UNESCAPED_SLASHES;

                if (! $this->ensureAscii) {
                    $flags |= JSON_UNESCAPED_UNICODE;
                }

                $encoded = json_encode($decoded, $flags);

                if ($encoded !== false) {
                    $this->output = $encoded;
                    $encodedViaRoundTrip = true;
                }
            }
        }

        if (! $encodedViaRoundTrip && $this->output !== '' && ! json_validate($this->output)) {
            throw JsonRepairException::invalidJsonAfterRepair($this->output);
        }

        return $this->output;
    }

    private function pushObjectKeyScope(): void
    {
        $this->objectKeysStack[] = [];
    }

    private function handleDuplicateKey(string $keyName): bool
    {
        if (! $this->duplicateKeyPolicy instanceof DuplicateKeyPolicy || $keyName === '' || $this->objectKeysStack === []) {
            return false;
        }

        $depth = count($this->objectKeysStack) - 1;

        if (! isset($this->objectKeysStack[$depth][$keyName])) {
            $this->objectKeysStack[$depth][$keyName] = $this->currentKeyStart;

            return false;
        }

        if ($this->duplicateKeyPolicy === DuplicateKeyPolicy::KeepFirst) {
            $this->log('Skipping duplicate key (keep-first policy)', [
                'key' => $keyName,
            ]);
            $this->removeCurrentKey();
            $this->skipNextValue = true;

            return true;
        }

        // Keep-last is resolved during finalization: the duplicate value is emitted
        // normally and json_decode() retains the final occurrence. Surgically removing
        // the earlier occurrence here would also delete any intervening keys.
        $this->log('Keeping last duplicate key (keep-last policy)', [
            'key' => $keyName,
        ]);
        $this->objectKeysStack[$depth][$keyName] = $this->currentKeyStart;

        return false;
    }

    private function extractCompletedKeyName(): string
    {
        if ($this->currentKeyStart < 0) {
            return '';
        }

        $segment = substr($this->output, $this->currentKeyStart);

        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $segment, $matches) !== 1) {
            return '';
        }

        $decoded = json_decode('"' . $matches[1] . '"');

        return is_string($decoded) ? $decoded : $matches[1];
    }

    private function skipValueAt(string $json, int $i): int
    {
        $length = strlen($json);

        while ($i < $length && ctype_space($json[$i])) {
            $i++;
        }

        if ($i >= $length) {
            return $i;
        }

        $char = $json[$i];

        if ($char === '"' || $char === "'") {
            $delimiter = $char;
            $i++;

            while ($i < $length) {
                if ($json[$i] === '\\') {
                    $i += 2;
                    continue;
                }

                if ($json[$i] === $delimiter) {
                    return $i + 1;
                }

                $i++;
            }

            return $i;
        }

        if ($char === '{' || $char === '[') {
            $depth = 0;

            while ($i < $length) {
                $current = $json[$i];

                if ($current === '"' || $current === "'") {
                    $delimiter = $current;
                    $i++;

                    while ($i < $length) {
                        if ($json[$i] === '\\') {
                            $i += 2;

                            continue;
                        }

                        if ($json[$i] === $delimiter) {
                            $i++;

                            break;
                        }

                        $i++;
                    }

                    continue;
                }

                if ($current === '{' || $current === '[') {
                    $depth++;
                } elseif ($current === '}' || $current === ']') {
                    $depth--;

                    if ($depth === 0) {
                        return $i + 1;
                    }
                }

                $i++;
            }

            return $i;
        }

        while ($i < $length && ! in_array($json[$i], [',', '}', ']'], true)) {
            $i++;
        }

        return $i;
    }
}
