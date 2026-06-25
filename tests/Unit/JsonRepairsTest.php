<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Tests\Unit;

use Cortex\JsonRepair\JsonRepairer;

use function Cortex\JsonRepair\json_repair;

covers(JsonRepairer::class);

describe('JSON repairs', function (): void {
    it('passes through valid JSON unchanged', function (string $json): void {
        $result = json_repair($json);
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true))->toBe(json_decode($json, true));
    })->with('valid_json');

    it('handles non-JSON strings', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect($result)->toBe($expected);

        if ($result !== '') {
            expect(json_validate($result))->toBeTrue();
            expect(json_decode($result, true))->toBe([]);
        }
    })->with('parse_string');

    it('repairs single quotes to double quotes', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('single_quotes_to_double');

    it('repairs unquoted keys', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('unquoted_keys');

    it('repairs missing quotes around keys', function (): void {
        $result = json_repair('{key: "value"}');
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true)['key'])->toBe('value');
    });

    it('handles mixed single and double quotes', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('mixed_quotes');

    it('handles quotes inside string values', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);
    })->with('quotes_inside_strings');

    it('repairs trailing commas', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('trailing_commas');

    it('repairs missing commas', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('missing_commas');

    it('repairs missing colons', function (): void {
        $result = json_repair('{"key" "value"}');
        expect(json_validate($result))->toBeTrue();
        expect(json_decode($result, true)['key'])->toBe('value');
    });

    it('repairs missing closing brackets', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('missing_closing_brackets');

    it('repairs missing closing braces', function (string $input, string $expectedPath, string $expectedValue): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);

        $value = $decoded;
        foreach (explode('.', $expectedPath) as $key) {
            $value = $value[$key];
        }

        expect($value)->toBe($expectedValue);
    })->with('missing_closing_braces');

    it('repairs missing values', function (string $input, string $expected): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        expect($result)->toBe($expected);

        $decoded = json_decode($result, true);
        expect($decoded)->toBe(json_decode($expected, true));
    })->with('missing_values');

    it('handles missing keys in objects', function (): void {
        $input = '{: "value"}';
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();
        $decoded = json_decode($result, true);
        expect($decoded)->toBeArray();
        expect($decoded)->toHaveKey('value');
        expect($decoded['value'])->toBe('');
    });
});
