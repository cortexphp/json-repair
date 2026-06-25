<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Tests\Unit;

use Cortex\JsonRepair\JsonRepairer;
use ColinODell\PsrTestLogger\TestLogger;

use function Cortex\JsonRepair\json_repair;

covers(JsonRepairer::class);

describe('Logging', function (): void {
    it('logs nothing for valid JSON', function (): void {
        $logger = new TestLogger();

        $result = json_repair('{"key": "value"}', logger: $logger);

        expect($logger->hasDebug('JSON is already valid, returning as-is'))->toBeTrue();
        expect($logger->records)->toHaveCount(1);
        expect($result)->toBe('{"key": "value"}');
    });

    it('logs repair actions for unclosed strings and brackets', function (): void {
        $logger = new TestLogger();

        $result = json_repair('{"key": "value', logger: $logger);

        expect($logger->hasDebug('Starting JSON repair'))->toBeTrue();
        expect($logger->hasDebug('Adding missing closing quote for unclosed string'))->toBeTrue();
        expect($logger->hasDebug('Adding missing closing bracket/brace'))->toBeTrue();

        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe('{"key": "value"}');
    });

    it('logs quote conversions and boolean normalization', function (): void {
        $logger = new TestLogger();

        $result = json_repair("{'active': True}", logger: $logger);

        expect($logger->hasDebug('Converting single-quoted key to double quotes'))->toBeTrue();
        expect($logger->hasDebugThatPasses(
            fn(array $record): bool => $record['message'] === 'Normalizing boolean/null value'
            && $record['context']['from'] === 'True'
            && $record['context']['to'] === 'true',
        ))->toBeTrue();

        expect($result)->toBe('{"active": true}');
    });

    it('logs unquoted key and value repairs', function (): void {
        $logger = new TestLogger();

        $result = json_repair('{name: John}', logger: $logger);

        expect($logger->hasDebug('Adding quotes around unquoted key'))->toBeTrue();
        expect($logger->hasDebug('Found unquoted string value, adding quotes'))->toBeTrue();

        expect($result)->toBe('{"name": "John"}');
    });

    it('logs missing comma and colon insertions', function (): void {
        $logger = new TestLogger();

        $result = json_repair('{"a": 1 "b" 2}', logger: $logger);

        expect($logger->hasDebug('Inserting missing comma'))->toBeTrue();
        expect($logger->hasDebug('Inserting missing colon after key'))->toBeTrue();

        expect(json_validate($result))->toBeTrue();
    });

    it('logs context with position information', function (): void {
        $logger = new TestLogger();

        json_repair('{"key": value}', logger: $logger);

        // Verify that log entries include position and context
        expect($logger->hasDebugThatPasses(
            fn(array $record): bool => isset($record['context']['position'])
            && isset($record['context']['context'])
            && str_contains((string) $record['context']['context'], '>>>'),
        ))->toBeTrue();
    });

    it('logs markdown extraction', function (): void {
        $logger = new TestLogger();

        $result = json_repair('```json {"key": "value"} ```', logger: $logger);

        expect($logger->hasDebug('Extracted JSON from markdown code block'))->toBeTrue();
        expect($result)->toBe('{"key": "value"}');
    });

    it('logs omitEmptyValues actions', function (): void {
        $logger = new TestLogger();

        $result = json_repair('{"a": 1, "b": }', omitEmptyValues: true, logger: $logger);

        expect($logger->hasDebug('Removing key with missing value (omitEmptyValues enabled)'))->toBeTrue();
        expect($result)->toBe('{"a": 1}');
    });

    it('works with JsonRepairer class and setLogger', function (): void {
        $logger = new TestLogger();

        $repairer = new JsonRepairer("{'key': 'value'}");
        $repairer->setLogger($logger);

        $result = $repairer->repair();

        expect($logger->hasDebugRecords())->toBeTrue();
        expect($result)->toBe('{"key": "value"}');
    });
});
