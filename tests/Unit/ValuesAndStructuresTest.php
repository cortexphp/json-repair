<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Tests\Unit;

use Cortex\JsonRepair\JsonRepairer;

use function Cortex\JsonRepair\json_repair;

covers(JsonRepairer::class);

describe('Values and structures', function (): void {
    it('repairs non-standard booleans and null', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('booleans_and_null');

    it('handles standalone booleans and null', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect($result)->toBe($expected);

        if ($result !== '') {
            expect(json_validate($result))->toBeTrue();
        }
    })->with('standalone_booleans_null');

    it('handles numbers correctly', function (string $input, int|float|string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();

        if (is_string($expected)) {
            return;
        }

        expect(json_decode($result, true)['key'])->toBe($expected);
    })->with('numbers');

    it('repairs invalid numbers', function (string $input, string $expectedJson, int|float $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expectedJson);
        expect(json_decode($result, true)['key'])->toBe($expected);
    })->with('invalid_numbers');

    it(
        'handles strings with special characters',
        function (string $input, string $expectedKey, string $expectedValue): void {
            $result = json_repair($input);
            expect(json_validate($result))->toBeTrue();
            $decoded = json_decode($result, true);
            expect($decoded[$expectedKey])->toBe($expectedValue);
        },
    )->with('special_characters');

    it('handles escape sequences', function (string $input, string $expectedValue): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBeArray();
        expect($decoded)->toHaveKey('key');
        expect($decoded['key'])->toBe($expectedValue);
    })->with('escape_sequences');

    it('handles advanced escaping cases', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('advanced_escaping');

    it('escapes unicode characters when ensureAscii is true', function (): void {
        $result = json_repair("{'city':'上海'}");
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe('{"city":"\\u4e0a\\u6d77"}');

        $decoded = json_decode($result, true);
        expect($decoded['city'])->toBe('上海');
    });

    it('handles unicode characters when ensureAscii is false', function (): void {
        $input = "{'test_中国人_ascii':'统一码'}";
        $result = json_repair($input, ensureAscii: false);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toContain('统一码');
        expect($result)->toContain('test_中国人_ascii');

        $decoded = json_decode($result, true);
        expect($decoded)->toHaveKey('test_中国人_ascii');
        expect($decoded['test_中国人_ascii'])->toBe('统一码');
    });

    it('handles empty strings as values', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('empty_strings');

    it('handles nested structures', function (string $input): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe(json_decode($input, true));
    })->with('nested_structures');

    it('handles empty structures', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('empty_structures');

    it('handles arrays with mixed types', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('mixed_type_arrays');
});
