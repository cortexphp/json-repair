<?php

declare(strict_types=1);

namespace Cortex\JsonRepair\Tests\Unit;

use Cortex\JsonRepair\JsonRepair;

use function Cortex\JsonRepair\json_repair;

covers(JsonRepair::class);

it('passes through valid JSON unchanged', function (string $json): void {
    $result = json_repair($json);
    expect(json_validate($result))->toBeTrue();
    expect(json_decode($result, true))->toBe(json_decode($json, true));
})->with([
    '{"name": "John", "age": 30, "city": "New York"}',
    '{"employees":["John", "Anna", "Peter"]}',
    '{"key": "value:value"}',
    '{"text": "The quick brown fox,"}',
    '{"text": "The quick brown fox won\'t jump"}',
    '{"key": ""}',
    '{"key1": {"key2": [1, 2, 3]}}',
    '{"key": 12345678901234567890}',
]);

it('repairs single quotes to double quotes', function (string $input, array $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);
    foreach ($expected as $key => $value) {
        expect($decoded[$key])->toBe($value);
    }
})->with([
    'single key-value' => [
        "{'key': 'value'}", [
            'key' => 'value',
        ]],
    'multiple key-values' => [
        "{'name': 'John', 'age': 30}", [
            'name' => 'John',
            'age' => 30,
        ]],
]);

it('repairs unquoted keys', function (string $input, array $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);
    foreach ($expected as $key => $value) {
        expect($decoded[$key])->toBe($value);
    }
})->with([
    'single unquoted key' => [
        '{key: "value"}', [
            'key' => 'value',
        ]],
    'multiple unquoted keys' => [
        '{name: "John", age: 30}', [
            'name' => 'John',
            'age' => 30,
        ]],
]);

it('repairs missing quotes around keys', function (): void {
    $result = json_repair('{key: "value"}');
    expect(json_validate($result))->toBeTrue();
    expect(json_decode($result, true)['key'])->toBe('value');
});

it('repairs trailing commas', function (string $input, array $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);
    expect($decoded)->toBe($expected);
})->with([
    'object with trailing comma' => [
        '{"key": "value",}', [
            'key' => 'value',
        ]],
    'object with multiple keys and trailing comma' => [
        '{"key1": "v1", "key2": "v2",}', [
            'key1' => 'v1',
            'key2' => 'v2',
        ]],
    'array with trailing comma' => ['[1, 2, 3,]', [1, 2, 3]],
]);

it('repairs missing commas', function (string $input, array $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);
    expect($decoded)->toBe($expected);
})->with([
    'object missing comma' => [
        '{"key1": "v1" "key2": "v2"}', [
            'key1' => 'v1',
            'key2' => 'v2',
        ]],
    'array missing commas' => ['["a" "b" "c"]', ['a', 'b', 'c']],
]);

it('repairs missing colons', function (): void {
    $result = json_repair('{"key" "value"}');
    expect(json_validate($result))->toBeTrue();
    expect(json_decode($result, true)['key'])->toBe('value');
});

it('repairs missing closing brackets', function (string $input, array $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);
    expect($decoded)->toBe($expected);
})->with([
    'object missing closing brace' => [
        '{"key": "value"', [
            'key' => 'value',
        ]],
    'array missing closing bracket' => ['["a", "b"', ['a', 'b']],
]);

it('repairs missing closing braces', function (string $input, string $expectedPath, string $expectedValue): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);
    $keys = explode('.', $expectedPath);
    $value = $decoded;
    foreach ($keys as $key) {
        $value = $value[$key];
    }

    expect($value)->toBe($expectedValue);
})->with([
    'simple object' => ['{"key": "value"', 'key', 'value'],
    'nested object' => ['{"key1": {"key2": "value"', 'key1.key2', 'value'],
]);

it('repairs missing values', function (string $input, array $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);
    expect($decoded)->toBe($expected);
})->with([
    'single missing value' => [
        '{"key": }', [
            'key' => '',
        ]],
    'multiple keys with missing value' => [
        '{"key1": "v1", "key2": }', [
            'key1' => 'v1',
            'key2' => '',
        ]],
]);

it('repairs non-standard booleans and null', function (string $input, mixed $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);

    if (is_array($expected)) {
        expect($decoded)->toBe($expected);
    } else {
        expect($decoded['key'])->toBe($expected);
    }
})->with([
    'capitalized True' => ['{"key": True}', true],
    'capitalized False' => ['{"key": False}', false],
    'capitalized None' => ['{"key": None}', null],
    'JSON true' => ['{"key": true}', true],
    'JSON false' => ['{"key": false}', false],
    'JSON null' => ['{"key": null}', null],
    'array with capitalized booleans' => ['[True, False, None]', [true, false, null]],
]);

it('handles nested structures', function (string $input): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    expect(json_decode($result, true))->toBe(json_decode($input, true));
})->with([
    '{"key1": {"key2": [1, 2, 3]}}',
    '{"employees":["John", "Anna", "Peter"]}',
]);

it('handles empty structures', function (string $input): void {
    expect(json_validate(json_repair($input)))->toBeTrue();
})->with([
    '{}',
    '[]',
    '{"key": []}',
    '{"key": {}}',
]);

it('handles numbers correctly', function (string $input, int|float|string $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();

    if (is_string($expected)) {
        // For large numbers, just validate they're valid JSON
        expect(json_validate($result))->toBeTrue();
    } else {
        expect(json_decode($result, true)['key'])->toBe($expected);
    }
})->with([
    'positive integer' => ['{"key": 123}', 123],
    'negative integer' => ['{"key": -123}', -123],
    'decimal' => ['{"key": 123.456}', 123.456],
    'scientific notation' => ['{"key": 123e10}', 'validate_only'],
    'large integer' => ['{"key": 12345678901234567890}', 'validate_only'],
]);

it('handles multiple JSON objects', function (string $input, ?string $expectedKey, ?string $expectedValue): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();

    if ($expectedKey !== null) {
        $decoded = json_decode($result, true);

        if ($expectedValue !== null) {
            expect($decoded[$expectedKey])->toBe($expectedValue);
        } else {
            expect($decoded)->toBeArray();
        }
    }
})->with([
    'empty array and object' => ['[]{}', null, null],
    'array then object' => ['[]{"key":"value"}', 'key', 'value'],
    'object then array' => ['{"key":"value"}[1,2,3,True]', null, null],
]);

it(
    'extracts JSON from markdown code blocks',
    function (string $input, ?string $expectedKey, ?string $expectedValue): void {
        $result = json_repair($input);
        expect(json_validate($result))->toBeTrue();

        if ($expectedKey !== null) {
            expect(json_decode($result, true)[$expectedKey])->toBe($expectedValue);
        }
    },
)->with([
    'single code block' => ['lorem ```json {"key":"value"} ``` ipsum', 'key', 'value'],
    'multiple code blocks' => ['```json {"key":"value"} ``` ```json [1,2,3,True] ```', null, null],
]);

it('handles strings with special characters', function (string $input): void {
    expect(json_validate(json_repair($input)))->toBeTrue();
})->with([
    '{"text": "The quick brown fox,"}',
    '{"text": "The quick brown fox won\'t jump"}',
    '{"key": "value:value"}',
]);

it('handles unicode characters when ensureAscii is false', function (): void {
    $input = "{'test_中国人_ascii':'统一码'}";
    $result = json_repair($input, ensureAscii: false);
    expect($result)->toContain('统一码');
    expect(json_validate($result))->toBeTrue();
});

it('handles escape sequences', function (string $input, string $expected): void {
    expect(json_repair($input))->toBe($expected);
})->with([
    'newline' => ['{"key": "value\\nvalue"}', '{"key": "value\\nvalue"}'],
    'tab' => ['{"key": "value\\tvalue"}', '{"key": "value\\tvalue"}'],
    'escaped quote' => ['{"key": "value\\"value"}', '{"key": "value\\"value"}'],
]);

it('works with RepairJson class directly', function (): void {
    $repairer = new JsonRepair("{'key': 'value'}");
    $result = $repairer->repair();
    expect(json_validate($result))->toBeTrue();
    expect(json_decode($result, true)['key'])->toBe('value');
});

it('can decode repaired JSON', function (): void {
    $repairer = new JsonRepair("{'key': 'value', 'number': 123}");
    $decoded = $repairer->decode();

    expect($decoded)->toBeArray();
    expect($decoded['key'])->toBe('value');
    expect($decoded['number'])->toBe(123);
});

it('handles complex nested structures', function (): void {
    $input = '{"resourceType": "Bundle", "id": "1", "type": "collection", "entry": [{"resource": {"resourceType": "Patient", "id": "1", "name": [{"use": "official", "family": "Corwin", "given": ["Keisha", "Sunny"], "prefix": ["Mrs."}, {"use": "maiden", "family": "Goodwin", "given": ["Keisha", "Sunny"], "prefix": ["Mrs."]}]}}]}';
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();

    $decoded = json_decode($result, true);
    expect($decoded)->toBeArray();
    expect($decoded['resourceType'])->toBe('Bundle');
});

it('handles strings with quotes inside', function (): void {
    $input = '{\n"html": "<h3 id="aaa">Waarom meer dan 200 Technical Experts - "Passie voor techniek"?</h3>"}';
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
});

it('handles arrays with mixed types', function (string $input, array $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    expect(json_decode($result, true))->toBe($expected);
})->with([
    'JSON booleans and null' => ['[1, "two", true, false, null]', [1, 'two', true, false, null]],
    'capitalized booleans and null' => ['[True, False, None, "string", 123]', [true, false, null, 'string', 123]],
]);

it('handles empty strings as values', function (string $input, array $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);
    expect($decoded)->toBe($expected);
})->with([
    'incomplete empty string' => [
        '{"key": ""', [
            'key' => '',
        ]],
    'complete with empty string' => [
        '{"key1": "", "key2": "value"}', [
            'key1' => '',
            'key2' => 'value',
        ]],
]);

it('handles missing keys in objects', function (): void {
    // This is a tricky case - missing key before colon
    $input = '{: "value"}';
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
});

it('handles incomplete JSON at end of string', function (string $input, array $expected): void {
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    $decoded = json_decode($result, true);
    expect($decoded)->toBe($expected);
})->with([
    'incomplete string value' => [
        '{"key": "val', [
            'key' => 'val',
        ]],
    'missing value' => [
        '{"key": ', [
            'key' => '',
        ]],
    'incomplete array' => ['["a", "b', ['a', 'b']],
]);

it('handles whitespace normalization', function (): void {
    $input = '{"key"   :   "value"   ,   "key2"   :   "value2"}';
    $result = json_repair($input);
    expect(json_validate($result))->toBeTrue();
    // The output may have normalized whitespace
    expect($result)->toContain('"key"');
    expect($result)->toContain('"value"');
});
